<?php

namespace App\Http\Controllers;

use App\Enums\AdvanceStatus;
use App\Enums\CatalogStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\PricingMode;
use App\Enums\QuotationStatus;
use App\Http\Requests\Invoices\StoreAdjustmentNoteRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Mail\InvoicePdfMail;
use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Models\ChargeType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceTemplate;
use App\Models\MetalRate;
use App\Models\RecurringProfile;
use App\Models\User;
use App\Services\CreditLimitService;
use App\Services\DiscountApprovalService;
use App\Services\EInvoiceService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceCloner;
use App\Services\QuotationFollowUpService;
use App\Services\QuotationService;
use App\Services\ReQuoteService;
use App\Services\SubscriptionService;
use App\Support\Attributes;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceCalculationService $calculator,
        private readonly QuotationService $quotations,
        private readonly CreditLimitService $credit,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->only(['search', 'status', 'customer_id', 'document_type']);

        $invoices = Invoice::query()
            ->with('customer:id,full_name,mobile_number')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($cq) => $cq->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when(($filters['document_type'] ?? 'all') !== 'all', fn ($query) => $query->where('document_type', $filters['document_type']))
            ->latest('invoice_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'usesJewelryDocuments' => Industry::usesWeightFields(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Invoice::class);

        // A catalog selection from the quotation builder seeds the form; the
        // draft is single-use so it cannot leak into a later invoice.
        $draft = $request->session()->pull('quotation_draft', []);

        return Inertia::render('invoices/create', [
            ...$this->formProps($request),
            'draftItems' => $draft,
        ]);
    }

    public function quotationStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($invoice->document_type->isQuotation(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(QuotationStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = QuotationStatus::from($validated['status']);

        try {
            $this->quotations->transition(
                $invoice,
                $newStatus,
                $request->user(),
                $validated['note'] ?? null,
            );

            if ($newStatus === QuotationStatus::Sent) {
                app(QuotationFollowUpService::class)->sendQuotation($invoice, $request->user()->id);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation updated.')]);

        return back();
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        if ($error = app(SubscriptionService::class)->invoiceQuotaError($request->user()->tenant)) {
            return back()->withErrors(['customer_id' => $error]);
        }

        $invoice = DB::transaction(function () use ($request) {
            $business = BusinessSetting::query()->lockForUpdate()->first() ?? BusinessSetting::current();

            // Loaded first: its active group decides what the unpriced lines
            // on this document are worth before anything is computed.
            $customer = Customer::query()
                ->with('group:id,discount_percent,is_active')
                ->findOrFail($request->validated('customer_id'));

            $computed = $this->calculator->calculate([
                ...$request->validated(),
                'rounding_mode' => $business->rounding_mode,
                'group_discount_percent' => $customer->groupDiscountPercent(),
            ]);
            $documentType = $request->validated('document_type', DocumentType::JewelryInvoice->value);

            // Credit and debit notes only exist against an invoice; they are
            // issued through storeNote(), never composed from scratch.
            abort_if(in_array($documentType, DocumentType::adjustmentValues(), true), 422,
                'Credit and debit notes are issued against an invoice.');

            // A credit-limited customer must be caught before the document is
            // written, so the block runs outside the write path.
            if ($this->credit->exceeds($customer, $documentType, (float) $computed['grand_total'])) {
                throw ValidationException::withMessages([
                    'customer_id' => $this->credit->errorMessage($customer, (float) $computed['grand_total']),
                ]);
            }

            $invoice = Invoice::create([
                'customer_id' => $request->validated('customer_id'),
                'document_type' => $documentType,
                'status' => in_array($documentType, [DocumentType::Quotation->value, DocumentType::DeliveryChallan->value], true)
                    ? InvoiceStatus::Draft
                    : InvoiceStatus::Unpaid,
                'invoice_number' => match ($documentType) {
                    DocumentType::Quotation->value => $business->nextQuotationNumber(),
                    DocumentType::DeliveryChallan->value => $business->nextChallanNumber(),
                    default => $business->nextInvoiceNumber(),
                },
                'invoice_date' => $request->validated('invoice_date'),
                'due_date' => $this->credit->dueDate(
                    $customer,
                    $request->validated('invoice_date'),
                    $request->validated('due_date'),
                ),
                // Only a quotation carries a validity window; storing it on an
                // invoice would make the expiry sweep pick up a sale.
                'quotation_valid_until' => $documentType === DocumentType::Quotation->value
                    ? $request->validated('quotation_valid_until')
                    : null,
                // Metal prices move daily, so record the day these
                // rates were struck. The customer accepts that rate;
                // the bill has to honour it later.
                'rate_locked_at' => $request->validated('rate_locked_at')
                    ?? $request->validated('invoice_date'),
                // Every quotation starts life at revision 1; editing a
                // draft never advances it.
                'revision_number' => 1,
                'revision_note' => null,
                'reference_number' => $request->validated('reference_number'),
                'salesperson_id' => $request->validated('salesperson_id'),
                'invoice_template_id' => $request->validated('invoice_template_id')
                    ?? InvoiceTemplate::currentDefault()->id,
                'pricing_mode' => $request->validated('pricing_mode'),
                'tax_mode' => $computed['tax_mode'],
                'tax_breakdown' => $computed['tax_breakdown'],
                'subtotal' => $computed['subtotal'],
                'charges_summary' => $computed['charges_summary'],
                'discount' => $computed['discount'],
                'tax' => $computed['tax'],
                'tds_rate' => $computed['tds_rate'],
                'tds_amount' => $computed['tds_amount'],
                'tcs_rate' => $computed['tcs_rate'],
                'tcs_amount' => $computed['tcs_amount'],
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'paid_amount' => 0,
                // TDS is withheld by the buyer at settlement, so it never
                // shows up as money the customer still owes.
                'balance_amount' => max($computed['grand_total'] - $computed['tds_amount'], 0),
                'notes' => $request->validated('notes'),
                'terms' => $request->validated('terms'),
                // Free-form per-invoice detail (site reference, job number).
                'attributes' => Attributes::clean($request->validated('attributes') ?? []),
                'created_by' => $request->user()->id,
            ]);

            $this->persistItemsAndCharges($invoice, $computed);

            InvoiceEvent::log($invoice, InvoiceEventType::Created, [], $request->user()->id);
            ActivityLog::record('invoice.created', $invoice, "Created invoice {$invoice->invoice_number}");

            return $invoice;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice created.')]);

        return to_route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'customer', 'salesperson', 'creator', 'template',
            'items' => fn ($q) => $q->with('charges'),
            'charges',
            'payments' => fn ($q) => $q->with('receiver:id,name')->latest('payment_date'),
            'shareLinks' => fn ($q) => $q->latest(),
            'notesLog' => fn ($q) => $q->with('creator:id,name')->latest(),
            'installments' => fn ($q) => $q->with('payment:id,amount,payment_date'),
            'parentInvoice:id,invoice_number,document_type,status',
            'discountApprover:id,name',
            'adjustmentNotes' => fn ($q) => $q->latest('invoice_date'),
        ]);

        $settings = BusinessSetting::current();

        $discountApprover = $invoice->discountApprover;

        return Inertia::render('invoices/show', [
            'invoice' => $invoice,
            'discountApproval' => [
                'threshold' => app(DiscountApprovalService::class)->threshold(),
                'percent' => app(DiscountApprovalService::class)->discountPercent($invoice),
                'required' => app(DiscountApprovalService::class)->required($invoice),
                'approved_by_name' => $discountApprover?->name,
                'approved_at' => $invoice->discount_approved_at?->toDateTimeString(),
                'approved_discount' => (float) ($invoice->discount_approved_discount ?? 0),
            ],
            'business' => [
                ...$settings->only(['default_currency', 'business_name']),
                'upi_id' => $settings->bank_details['upi_id'] ?? null,
            ],
            'installmentPlan' => [
                'planned_total' => (float) $invoice->installments->where('status', '!=', 'waived')->sum('amount'),
                'collected_total' => (float) $invoice->installments->where('status', 'paid')->sum('amount'),
                'count' => $invoice->installments->count(),
            ],
            'recurringProfile' => RecurringProfile::query()
                ->where('source_invoice_id', $invoice->id)
                ->first(['id', 'frequency', 'next_run_at', 'last_run_at', 'is_active']),
            // Advances this invoice's customer still holds, so the apply
            // dialog can open with real options instead of an empty picker.
            'availableAdvances' => $invoice->document_type->isPayable()
                ? $invoice->customer->advances()
                    ->where('status', AdvanceStatus::Available->value)
                    ->whereRaw('amount > applied_amount')
                    ->latest('advance_date')
                    ->get(['id', 'amount', 'applied_amount', 'advance_date', 'reference_number', 'notes'])
                : [],
        ]);
    }

    public function preview(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return $this->show($invoice);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        $invoice->load(['items.charges', 'charges']);

        return Inertia::render('invoices/edit', [
            ...$this->formProps($request),
            'invoice' => $invoice,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        abort_if($invoice->isLocked(), 422,
            'Issued, paid, or e-invoiced documents cannot be edited.');

        DB::transaction(function () use ($request, $invoice) {
            $business = BusinessSetting::query()->lockForUpdate()->first() ?? BusinessSetting::current();
            $customer = Customer::query()
                ->with('group:id,discount_percent,is_active')
                ->findOrFail($request->validated('customer_id'));

            $computed = $this->calculator->calculate([
                ...$request->validated(),
                'rounding_mode' => $business->rounding_mode,
                'group_discount_percent' => $customer->groupDiscountPercent(),
            ]);
            $documentType = $request->validated('document_type', $invoice->document_type->value);

            // Notes are simple one-line documents: corrections are made by
            // cancelling and re-issuing rather than editing history.
            abort_if($invoice->document_type->isAdjustment(), 422,
                'Credit and debit notes cannot be edited — cancel and issue a new one.');

            abort_if(in_array($documentType, DocumentType::adjustmentValues(), true), 422,
                'Invoices cannot be re-typed into credit or debit notes.');

            // Pass the existing invoice so its current balance is not counted
            // twice when re-saving.
            if ($this->credit->exceeds($customer, $documentType, (float) $computed['grand_total'], $invoice)) {
                throw ValidationException::withMessages([
                    'customer_id' => $this->credit->errorMessage($customer, (float) $computed['grand_total']),
                ]);
            }

            $invoice->update([
                'customer_id' => $request->validated('customer_id'),
                'document_type' => $documentType,
                'invoice_date' => $request->validated('invoice_date'),
                'due_date' => $this->credit->dueDate(
                    $customer,
                    $request->validated('invoice_date'),
                    $request->validated('due_date'),
                ),
                'quotation_valid_until' => $documentType === DocumentType::Quotation->value
                    ? $request->validated('quotation_valid_until')
                    : null,
                // A re-save must not silently re-date the price the
                // customer already saw, so an existing lock survives
                // unless staff deliberately change it.
                'rate_locked_at' => $request->validated('rate_locked_at')
                    ?? $invoice->rate_locked_at
                    ?? $request->validated('invoice_date'),
                // A quotation the customer has already seen is a
                // commitment: once it leaves draft, any edit is a new
                // revision, stamped with the reason staff were given.
                // Draft edits are free and keep the same number.
                'revision_number' => $this->isRevisionBump($invoice, $documentType)
                    ? (int) ($invoice->revision_number ?? 1) + 1
                    : (int) ($invoice->revision_number ?? 1),
                'revision_note' => $this->isRevisionBump($invoice, $documentType)
                    ? ($request->validated('revision_note') ?: 'Updated after sending to customer')
                    : $invoice->revision_note,
                'reference_number' => $request->validated('reference_number'),
                'salesperson_id' => $request->validated('salesperson_id'),
                'invoice_template_id' => $request->validated('invoice_template_id') ?? $invoice->invoice_template_id,
                'pricing_mode' => $request->validated('pricing_mode'),
                'tax_mode' => $computed['tax_mode'],
                'tax_breakdown' => $computed['tax_breakdown'],
                'subtotal' => $computed['subtotal'],
                'charges_summary' => $computed['charges_summary'],
                'discount' => $computed['discount'],
                'tax' => $computed['tax'],
                'tds_rate' => $computed['tds_rate'],
                'tds_amount' => $computed['tds_amount'],
                'tcs_rate' => $computed['tcs_rate'],
                'tcs_amount' => $computed['tcs_amount'],
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'notes' => $request->validated('notes'),
                'terms' => $request->validated('terms'),
                'attributes' => Attributes::clean($request->validated('attributes') ?? []),
            ]);

            // Full replace of items/charges is the simplest correct approach
            // for a form that submits the whole item list on every save.
            // invoice_item_charges cascade-deletes with their parent item.
            $invoice->items()->delete();
            $invoice->charges()->delete();

            $this->persistItemsAndCharges($invoice, $computed);

            $invoice->load('payments');
            $invoice->recalculatePaymentStatus();
            $invoice->save();

            // A quotation only earns a new revision once it has left the
            // privacy of a draft — the customer has seen it, so any
            // change is a real change to what they were shown.
            if ($this->isRevisionBump($invoice, $documentType)) {
                InvoiceEvent::log($invoice, InvoiceEventType::Updated, [
                    'action' => 'revision',
                    'revision' => $invoice->revision_number,
                    'note' => $invoice->revision_note,
                ], $request->user()->id);
            }

            InvoiceEvent::log($invoice, InvoiceEventType::Updated, [], $request->user()->id);
            ActivityLog::record('invoice.updated', $invoice, "Updated invoice {$invoice->invoice_number}");
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice updated.')]);

        return to_route('invoices.show', $invoice);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        abort_if($invoice->isLocked(), 422,
            'Issued, paid, or e-invoiced documents cannot be deleted.');

        // Notes travel with the invoice they correct — an orphaned credit
        // note would still claim to adjust a document that no longer exists.
        if (! $invoice->document_type->isAdjustment()) {
            $invoice->adjustmentNotes()->delete();
        }

        $invoice->delete();

        $this->recalculateParentOf($invoice);

        ActivityLog::record('invoice.deleted', $invoice, "Deleted invoice {$invoice->invoice_number}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice deleted.')]);

        return to_route('invoices.index');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        $invoice->update(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now()]);

        // A cancelled note must stop reducing (or adding to) the balance
        // immediately.
        $this->recalculateParentOf($invoice);

        InvoiceEvent::log($invoice, InvoiceEventType::Cancelled, [], auth()->id());
        ActivityLog::record('invoice.cancelled', $invoice, "Cancelled invoice {$invoice->invoice_number}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice cancelled.')]);

        return back();
    }

    /**
     * Issue a credit note or debit note against an invoice.
     *
     * The note is a real document — its own number, line item, tax and PDF —
     * but it is always built from the parent so the reference can never be
     * missing or point at the wrong tenant.
     */
    public function storeNote(StoreAdjustmentNoteRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        abort_unless($invoice->document_type->isPayable(), 422,
            'Credit and debit notes can only be issued against an invoice.');
        abort_if($invoice->status === InvoiceStatus::Cancelled, 422,
            'A cancelled invoice cannot be adjusted.');

        $validated = $request->validated();
        $type = DocumentType::from($validated['type']);

        $note = DB::transaction(function () use ($request, $invoice, $validated, $type) {
            $business = BusinessSetting::query()->lockForUpdate()->first() ?? BusinessSetting::current();

            // 'fixed' exists for every industry, but fall back to the first
            // allowed rate type rather than failing on a custom config.
            $allowedRateTypes = Industry::rateTypes($request->user()->tenant?->industry);
            $rateType = in_array('fixed', $allowedRateTypes, true) ? 'fixed' : $allowedRateTypes[0];

            $computed = $this->calculator->calculate([
                'customer_id' => $invoice->customer_id,
                'pricing_mode' => PricingMode::Manual->value,
                'tax_mode' => $invoice->tax_mode->value,
                'discount' => 0,
                'tax_rate' => 0,
                'rounding_mode' => $business->rounding_mode,
                'items' => [[
                    'item_name' => sprintf('%s against %s', $type->label(), $invoice->invoice_number),
                    'description' => $validated['reason'] ?? null,
                    'quantity' => 1,
                    'rate_type' => $rateType,
                    'rate' => $validated['amount'],
                    'tax_rate' => $validated['tax_rate'] ?? 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
                'invoice_charges' => [],
            ]);

            $note = Invoice::create([
                'customer_id' => $invoice->customer_id,
                'invoice_number' => $business->nextAdjustmentNumber($type),
                'invoice_date' => now()->toDateString(),
                'document_type' => $type,
                'status' => InvoiceStatus::Closed,
                'parent_invoice_id' => $invoice->id,
                'salesperson_id' => $invoice->salesperson_id,
                'invoice_template_id' => $invoice->invoice_template_id,
                'pricing_mode' => PricingMode::Manual->value,
                'tax_mode' => $computed['tax_mode'],
                'tax_breakdown' => $computed['tax_breakdown'],
                'subtotal' => $computed['subtotal'],
                'charges_summary' => $computed['charges_summary'],
                'discount' => $computed['discount'],
                'tax' => $computed['tax'],
                // A note is a value correction; it never carries withholding.
                'tds_rate' => 0,
                'tds_amount' => 0,
                'tcs_rate' => 0,
                'tcs_amount' => 0,
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                // A note is not money owed or received on its own; the whole
                // effect lives on the parent invoice's balance.
                'paid_amount' => 0,
                'balance_amount' => 0,
                'notes' => $validated['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->persistItemsAndCharges($note, $computed);

            InvoiceEvent::log($note, InvoiceEventType::Created, [
                'parent_invoice_id' => $invoice->id,
                'parent_invoice_number' => $invoice->invoice_number,
            ], $request->user()->id);
            ActivityLog::record('invoice.created', $note, "Issued {$type->label()} {$note->invoice_number}");

            $invoice->load('payments');
            $invoice->recalculatePaymentStatus();
            $invoice->save();

            return $note;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('%s issued.', $type->label())]);

        return to_route('invoices.show', $note);
    }

    /**
     * Re-derive the balance of the invoice a credit/debit note corrects.
     */
    protected function recalculateParentOf(Invoice $invoice): void
    {
        if ($invoice->parent_invoice_id === null) {
            return;
        }

        $parent = Invoice::query()->find($invoice->parent_invoice_id);

        if ($parent === null) {
            return;
        }

        $parent->load('payments');
        $parent->recalculatePaymentStatus();
        $parent->save();
    }

    public function convert(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($invoice->document_type === DocumentType::Quotation, 404);
        abort_if($invoice->converted_to_id !== null, 422, 'Quotation already converted.');

        $validated = $request->validate([
            'document_type' => ['nullable', Rule::enum(DocumentType::class), Rule::notIn([
                DocumentType::Quotation->value,
                ...DocumentType::adjustmentValues(),
            ])],
            'due_date' => ['nullable', 'date'],
            'apply_advances' => ['nullable', 'boolean'],
        ]);

        $industry = $invoice->tenant->industry ?? 'general_trade';
        $defaultType = Industry::usesWeightFields($industry) ? DocumentType::JewelryInvoice->value : DocumentType::GeneralInvoice->value;
        $documentType = $validated['document_type'] ?? $defaultType;

        $requiresApproval = app(DiscountApprovalService::class)->required($invoice);

        abort_if(
            $requiresApproval,
            422,
            'This quotation carries a discount of '.number_format(app(DiscountApprovalService::class)->discountPercent($invoice), 1).'%, above your '
                .number_format((float) BusinessSetting::current()->discount_approval_threshold, 1).'% approval limit. An admin must approve the discount before converting.'
        );

        $newInvoice = app(InvoiceCloner::class)->cloneAsNew(
            $invoice,
            $request->user()->id,
            $documentType,
            $validated['due_date'] ?? null
        );

        if (! empty($validated['apply_advances'])) {
            app(CustomerAdvanceController::class)->applyAvailableAdvancesToInvoice($newInvoice, $request->user());
        }

        $invoice->update(['status' => InvoiceStatus::Converted, 'converted_to_id' => $newInvoice->id]);

        // Close the quotation out in the lifecycle too.
        $this->quotations->transition($invoice, QuotationStatus::Converted, $request->user());

        ActivityLog::record('invoice.converted', $newInvoice, "Converted quotation {$invoice->invoice_number} to {$newInvoice->invoice_number}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation converted to invoice.')]);

        return to_route('invoices.show', $newInvoice);
    }

    public function duplicate(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        abort_unless($invoice->document_type->isQuotation(), 404);

        $newInvoice = app(InvoiceCloner::class)->cloneAsNew(
            $invoice,
            $request->user()->id,
            DocumentType::Quotation->value,
            null,
            true,
        );

        ActivityLog::record('invoice.created', $newInvoice, "Duplicated quotation {$invoice->invoice_number} as {$newInvoice->invoice_number}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation duplicated.')]);

        return to_route('invoices.show', $newInvoice);
    }

    /**
     * "Same again" — rebuild this document as a fresh quotation, with every
     * metal line re-priced at today's rate.
     *
     * The customer's repeat order is the most common thing that happens at
     * this counter, and retyping the quote is the slowest part of it.
     */
    public function reQuote(Request $request, Invoice $invoice, ReQuoteService $reQuotes): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        abort_if($invoice->document_type->isAdjustment(), 422, 'Adjustment notes cannot be re-quoted.');

        $invoice->loadMissing('items');

        if (! $reQuotes->canReQuote($invoice)) {
            return back()->withErrors(['document_type' => 'This document has no items to re-quote.']);
        }

        $quotation = $reQuotes->create($invoice, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Re-quoted from :number at today\'s metal rate.', ['number' => $invoice->invoice_number]),
        ]);

        return to_route('invoices.show', $quotation);
    }

    /**
     * An admin stands behind a discount that is over the configured
     * limit. Stamped onto the invoice, and carried onto the sales
     * invoice when the quotation converts.
     */
    public function approveDiscount(Request $request, Invoice $invoice, DiscountApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($request->user()->canDo(Permission::ManageSettings), 403);

        $approvals->approve($invoice, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discount approved.')]);

        return back();
    }

    public function generateEInvoice(Request $request, Invoice $invoice, EInvoiceService $einvoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_if($invoice->document_type->isQuotation(), 422, 'Quotations do not need e-invoices.');
        abort_if($invoice->document_type->isAdjustment(), 422, 'Credit and debit notes are not e-invoiced here.');

        try {
            $einvoice->generate($invoice, $request->user()->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['einvoice' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('E-invoice request recorded.')]);

        return back();
    }

    public function sendEmail(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('share', $invoice);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Mail::to($validated['email'])->send(
            new InvoicePdfMail($invoice, $validated['message'] ?? null)
        );

        InvoiceEvent::log(
            $invoice,
            InvoiceEventType::Sent,
            ['action' => 'emailed', 'to' => $validated['email']],
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice emailed.')]);

        return back();
    }

    /**
     * @param  array{items: array<int, array<string, mixed>>, invoice_charges: array<int, array<string, mixed>>, subtotal: float, discount: float, tax: float, round_off: float, grand_total: float, charges_summary: array}  $computed
     */
    protected function persistItemsAndCharges(Invoice $invoice, array $computed): void
    {
        foreach ($computed['items'] as $itemData) {
            $charges = $itemData['charges'];
            unset($itemData['charges'], $itemData['charges_total']);

            $item = $invoice->items()->create($itemData);

            foreach ($charges as $chargeData) {
                $item->charges()->create($chargeData);
            }
        }

        foreach ($computed['invoice_charges'] as $chargeData) {
            $invoice->charges()->create($chargeData);
        }
    }

    /**
     * Whether an edit to this document is a real revision of the
     * customer's version. Only quotations ever have a revision
     * history, and only once the customer has been shown one: a
     * draft is private, so editing it costs no revision.
     */
    protected function isRevisionBump(Invoice $invoice, string $documentType): bool
    {
        return $documentType === DocumentType::Quotation->value
            && in_array(
                $invoice->quotation_status?->value,
                [QuotationStatus::Sent->value, QuotationStatus::Accepted->value, QuotationStatus::Rejected->value, QuotationStatus::Expired->value],
                true,
            );
    }

    /**
     * Which document a "New…" link should open. A tenant's industry decides
     * the default, but ?document_type= lets an explicit button (New
     * quotation, New delivery challan) preselect it. A jewelry-only document
     * type is never preselected for a non-jewelry tenant.
     */
    protected function requestedDocumentType(Request $request, string $industry): DocumentType
    {
        $requested = DocumentType::tryFrom((string) $request->query('document_type', ''));

        // An adjustment is issued from the invoice it corrects, never
        // composed blank on the create form.
        if ($requested !== null && ! $requested->isAdjustment()) {
            if ($requested === DocumentType::JewelryInvoice && ! Industry::usesWeightFields($industry)) {
                return DocumentType::from(Industry::defaultDocumentType($industry));
            }

            return $requested;
        }

        return DocumentType::from(Industry::defaultDocumentType($industry));
    }

    /**
     * Shared reference data for the create/edit invoice form.
     *
     * @return array<string, mixed>
     */
    protected function formProps(Request $request): array
    {
        $business = BusinessSetting::current();
        $industry = $business->industryKey();

        return [
            'industry' => $industry,
            'industryConfig' => [
                'key' => $industry,
                'label' => Industry::label($industry),
                'description' => (string) Industry::config($industry)['description'],
                'uses_metal_rates' => Industry::usesMetalRates($industry),
                'uses_weight_fields' => Industry::usesWeightFields($industry),
                'uses_stone_fields' => Industry::usesStoneFields($industry),
                'document_type' => Industry::defaultDocumentType($industry),
                'pricing_mode' => Industry::defaultPricingMode($industry),
                'rate_types' => Industry::rateTypes($industry),
                'item_fields' => Industry::itemFields($industry),
                'template_flags' => Industry::templateFlags($industry),
                'charge_types' => Industry::config($industry)['charge_types'],
            ],
            'industries' => collect(Industry::all())
                ->map(fn (array $config, string $key) => [
                    'key' => $key,
                    'label' => $config['label'],
                    'description' => $config['description'],
                ])
                ->values()
                ->all(),
            'customers' => Customer::query()
                // Eager loaded so the form can price a line the way the
                // server will once it is stored, without a query per customer.
                ->with('group:id,discount_percent,is_active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'mobile_number', 'state_code', 'customer_group_id'])
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'mobile_number' => $customer->mobile_number,
                    'state_code' => $customer->state_code,
                    'group_discount_percent' => $customer->groupDiscountPercent(),
                ])
                ->values(),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'chargeTypes' => ChargeType::query()
                ->active()
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'calculation_type', 'applies_to', 'default_rate', 'is_taxable']),
            'catalogItems' => CatalogItem::query()
                ->where('status', CatalogStatus::Active->value)
                // Inactive variants are still sent: an invoice already
                // pointing at one has to render its selection back.
                ->with(['variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->orderBy('name')
                ->get([
                    'id', 'name', 'brand', 'item_code', 'model_number', 'hsn_code',
                    'size_label', 'finish', 'grade', 'specification', 'unit_label',
                    'metal_type', 'purity', 'rate_type', 'default_rate',
                    'default_net_weight', 'default_gross_weight',
                    'default_length', 'default_width', 'default_wastage_percent',
                    'attributes', 'description',
                ]),
            'invoiceTemplates' => InvoiceTemplate::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']),
            'metalRates' => MetalRate::latestRates()->map(fn (MetalRate $r) => [
                'id' => $r->id,
                'metal_type' => $r->metal_type,
                'purity' => $r->purity,
                'rate_date' => $r->rate_date->format('Y-m-d'),
                'rate_per_gram' => $r->rate_per_gram,
            ])->values(),
            'preselectedCustomerId' => $request->integer('customer_id') ?: null,
            'requestedDocumentType' => $this->requestedDocumentType($request, $industry),
            'defaults' => [
                'default_tax_rate' => (float) $business->default_tax_rate,
                'default_currency' => $business->default_currency,
                'business_state_code' => $business->state_code,
                // The live preview mirrors the server total, so it has to
                // round exactly the way this business does.
                'rounding_mode' => $business->rounding_mode,
                'invoice_number_preview' => sprintf(
                    '%s-%d-%05d',
                    $business->invoice_prefix,
                    now()->year,
                    max($business->next_invoice_sequence, $business->invoice_number_start),
                ),
            ],
        ];
    }
}

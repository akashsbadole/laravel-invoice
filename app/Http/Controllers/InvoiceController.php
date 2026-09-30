<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Models\ChargeType;
use App\Models\Customer;
use App\Mail\InvoicePdfMail;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceTemplate;
use App\Models\MetalRate;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceCalculationService $calculator) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'customer_id']);

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
            ->latest('invoice_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Invoice::class);

        return Inertia::render('invoices/create', $this->formProps($request));
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($request) {
            $business = BusinessSetting::query()->lockForUpdate()->find(1) ?? BusinessSetting::current();
            $computed = $this->calculator->calculate($request->validated());

            $invoice = Invoice::create([
                'customer_id' => $request->validated('customer_id'),
                'invoice_number' => $business->nextInvoiceNumber(),
                'invoice_date' => $request->validated('invoice_date'),
                'due_date' => $request->validated('due_date'),
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
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'paid_amount' => 0,
                'balance_amount' => $computed['grand_total'],
                'notes' => $request->validated('notes'),
                'terms' => $request->validated('terms'),
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
        ]);

        return Inertia::render('invoices/show', [
            'invoice' => $invoice,
            'business' => BusinessSetting::current()->only(['default_currency']),
        ]);
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

        DB::transaction(function () use ($request, $invoice) {
            $computed = $this->calculator->calculate($request->validated());

            $invoice->update([
                'customer_id' => $request->validated('customer_id'),
                'invoice_date' => $request->validated('invoice_date'),
                'due_date' => $request->validated('due_date'),
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
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'notes' => $request->validated('notes'),
                'terms' => $request->validated('terms'),
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

            InvoiceEvent::log($invoice, InvoiceEventType::Updated, [], $request->user()->id);
            ActivityLog::record('invoice.updated', $invoice, "Updated invoice {$invoice->invoice_number}");
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice updated.')]);

        return to_route('invoices.show', $invoice);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $invoice->delete();

        ActivityLog::record('invoice.deleted', $invoice, "Deleted invoice {$invoice->invoice_number}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice deleted.')]);

        return to_route('invoices.index');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        $invoice->update(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now()]);

        InvoiceEvent::log($invoice, InvoiceEventType::Cancelled, [], auth()->id());
        ActivityLog::record('invoice.cancelled', $invoice, "Cancelled invoice {$invoice->invoice_number}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice cancelled.')]);

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
     * Shared reference data for the create/edit invoice form.
     *
     * @return array<string, mixed>
     */
    protected function formProps(Request $request): array
    {
        $business = BusinessSetting::current();

        return [
            'customers' => Customer::query()
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'mobile_number', 'state_code']),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'chargeTypes' => ChargeType::query()
                ->active()
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'calculation_type', 'applies_to', 'default_rate', 'is_taxable']),
            'catalogItems' => CatalogItem::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'item_code', 'hsn_code', 'metal_type', 'purity', 'rate_type', 'default_rate', 'default_net_weight', 'default_gross_weight', 'description']),
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
            'defaults' => [
                'default_tax_rate' => (float) $business->default_tax_rate,
                'default_currency' => $business->default_currency,
                'business_state_code' => $business->state_code,
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

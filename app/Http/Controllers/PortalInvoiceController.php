<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PortalInvoiceController extends Controller
{
    public function __construct(private readonly InvoicePdfService $pdf) {}

    public function index(Request $request): Response
    {
        $customer = $request->attributes->get('portalCustomer');

        $saleTypes = array_column(
            array_filter(DocumentType::cases(), fn (DocumentType $type) => $type->isSale()),
            'value',
        );

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('document_type', $saleTypes)
            ->latest('invoice_date')
            ->paginate(15)
            ->through(fn (Invoice $invoice) => $invoice->only([
                'id', 'invoice_number', 'invoice_date', 'due_date', 'status',
                'document_type', 'grand_total', 'paid_amount', 'balance_amount',
            ]));

        return Inertia::render('portal/dashboard', [
            'customer' => $customer->only(['full_name', 'email', 'mobile_number']),
            'invoices' => $invoices,
            'totals' => [
                'invoiced' => (float) $customer->totalInvoiced(),
                'paid' => (float) $customer->totalPaid(),
                'outstanding' => (float) $customer->totalOutstanding(),
            ],
        ]);
    }

    public function quotations(Request $request): Response
    {
        $customer = $request->attributes->get('portalCustomer');

        $quotations = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('document_type', DocumentType::Quotation->value)
            ->where(function ($q) {
                $q->whereNull('quotation_status')
                    ->orWhere('quotation_status', '!=', \App\Enums\QuotationStatus::Draft->value);
            })
            ->where('status', '!=', \App\Enums\InvoiceStatus::Draft->value)
            ->latest('invoice_date')
            ->get()
            ->map(fn (Invoice $quotation) => [
                'id' => $quotation->id,
                'invoice_number' => $quotation->invoice_number,
                'invoice_date' => $quotation->invoice_date,
                'valid_until' => $quotation->quotation_valid_until,
                'status' => app(QuotationService::class)->currentStatus($quotation)->value,
                'status_label' => app(QuotationService::class)->currentStatus($quotation)->label(),
                'is_open' => app(QuotationService::class)->currentStatus($quotation)->isOpen(),
                'grand_total' => (float) $quotation->grand_total,
                'can_decide' => $quotation->converted_to_id === null,
            ]);

        return Inertia::render('portal/quotations', [
            'customer' => $customer->only(['full_name', 'email', 'mobile_number']),
            'quotations' => $quotations,
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $customer = $request->attributes->get('portalCustomer');
        abort_unless($invoice->customer_id === $customer->id, 404);

        $invoice->load(['items.charges', 'charges', 'payments', 'template']);

        $settings = BusinessSetting::forTenant($invoice->tenant_id);

        return Inertia::render('portal/invoices/show', [
            'invoice' => $this->sanitizePublicInvoice($invoice),
            'business' => [
                ...$settings->only([
                    'business_name', 'address', 'phone', 'email', 'tax_number',
                ]),
                'upi_id' => $settings->bank_details['upi_id'] ?? null,
            ],
        ]);
    }

    protected function sanitizePublicInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'rate_locked_at' => $invoice->rate_locked_at?->toDateString(),
            'revision_number' => $invoice->revision_number,
            'document_type' => $invoice->document_type?->value,
            'status' => $invoice->status?->value,
            'subtotal' => (float) $invoice->subtotal,
            'charges_summary' => $invoice->charges_summary,
            'discount' => (float) $invoice->discount,
            'tax' => (float) $invoice->tax,
            'tax_breakdown' => $invoice->tax_breakdown,
            'tcs_rate' => (float) $invoice->tcs_rate,
            'tcs_amount' => (float) $invoice->tcs_amount,
            'round_off' => (float) $invoice->round_off,
            'grand_total' => (float) $invoice->grand_total,
            'paid_amount' => (float) $invoice->paid_amount,
            'balance_amount' => (float) $invoice->balance_amount,
            'terms' => $invoice->terms,
            'converted_to_id' => $invoice->converted_to_id,
            'quotation_response' => $invoice->quotation_response,
            'customer' => $invoice->customer ? $invoice->customer->only([
                'full_name', 'mobile_number', 'email', 'address', 'tax_number',
            ]) : null,
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_label' => $item->unit_label,
                'rate_type' => $item->rate_type?->value,
                'rate' => (float) $item->rate,
                'net_weight' => $item->net_weight !== null ? (float) $item->net_weight : null,
                'gross_weight' => $item->gross_weight !== null ? (float) $item->gross_weight : null,
                'wastage_percent' => $item->wastage_percent !== null ? (float) $item->wastage_percent : null,
                'making_charge' => $item->making_charge !== null ? (float) $item->making_charge : null,
                'item_total' => (float) $item->item_total,
                'charges' => $item->charges->map(fn ($c) => [
                    'id' => $c->id,
                    'charge_name' => $c->charge_name,
                    'amount' => (float) $c->amount,
                ])->values()->all(),
            ])->values()->all(),
            'payments' => $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date?->toDateString(),
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method?->value,
                'reference_number' => $p->reference_number,
            ])->values()->all(),
            'template' => $invoice->template ? [
                'id' => $invoice->template->id,
                'name' => $invoice->template->name,
                'layout_config' => $invoice->template->layout_config,
            ] : null,
        ];
    }

    public function pdf(Request $request, Invoice $invoice): SymfonyResponse
    {
        $customer = $request->attributes->get('portalCustomer');
        abort_unless($invoice->customer_id === $customer->id, 404);

        $pdf = Pdf::loadView($this->pdf->viewName($invoice), $this->pdf->viewData($invoice, withShareLink: false))
            ->setPaper('a4');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }
}

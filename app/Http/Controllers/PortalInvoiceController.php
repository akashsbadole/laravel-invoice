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

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
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
            'invoice' => $invoice,
            'business' => [
                ...$settings->only([
                    'business_name', 'address', 'phone', 'email', 'tax_number',
                ]),
                'upi_id' => $settings->bank_details['upi_id'] ?? null,
            ],
        ]);
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

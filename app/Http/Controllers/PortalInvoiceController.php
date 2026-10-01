<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
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

        $pdf = Pdf::loadView('pdf.invoice', $this->pdf->viewData($invoice, withShareLink: false))
            ->setPaper('a4');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }
}

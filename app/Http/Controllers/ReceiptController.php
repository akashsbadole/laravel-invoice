<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Industry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thermal (58 mm / 80 mm) receipts. The HTML view is print-ready for browser
 * printing to a thermal printer driver; ?pdf=1 renders the same layout as a PDF.
 */
class ReceiptController extends Controller
{
    public function invoice(Request $request, Invoice $invoice): View|Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['customer', 'items.charges', 'payments']);
        $business = BusinessSetting::current();
        $width = $this->width($request, $business);
        $data = [
            'invoice' => $invoice,
            'business' => $business,
            'width' => $width,
            'isPdf' => $request->boolean('pdf'),
            'showWeights' => Industry::usesWeightFields($business->industryKey()),
        ];

        if (! $request->boolean('pdf')) {
            return view('receipts.invoice', $data);
        }

        $height = 360
            + $invoice->items->count() * 78
            + count($invoice->charges_summary ?? []) * 18
            + count($invoice->tax_breakdown ?? []) * 18
            + $invoice->payments->count() * 18;

        return $this->pdf('receipts.invoice', $data, $width, $height, $invoice->invoice_number);
    }

    public function payment(Request $request, Payment $payment): View|Response
    {
        $payment->load(['invoice.customer', 'receiver']);
        abort_unless($payment->invoice, 404);
        Gate::authorize('view', $payment->invoice);

        $business = BusinessSetting::current();
        $width = $this->width($request, $business);
        $data = [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'business' => $business,
            'width' => $width,
            'receiptNumber' => sprintf('RCPT-%06d', $payment->id),
            'isPdf' => $request->boolean('pdf'),
        ];

        if (! $request->boolean('pdf')) {
            return view('receipts.payment', $data);
        }

        return $this->pdf('receipts.payment', $data, $width, 460, $data['receiptNumber']);
    }

    protected function width(Request $request, BusinessSetting $business): string
    {
        $width = (string) $request->query('width', $business->receipt_width);

        return in_array($width, ['58', '80'], true) ? $width : '80';
    }

    /**
     * @param  array<string,mixed>  $data
     */
    protected function pdf(string $view, array $data, string $width, int $heightPt, string $name): Response
    {
        $widthPt = (int) round(((int) $width) * 2.8346);

        return Pdf::loadView($view, $data)->setPaper([0, 0, $widthPt, $heightPt])->stream("receipt-{$name}.pdf");
    }
}

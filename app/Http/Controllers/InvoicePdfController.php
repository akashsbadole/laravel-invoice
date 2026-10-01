<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Services\InvoicePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function __construct(private readonly InvoicePdfService $pdf) {}

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return $this->render($invoice);
    }

    public function public(string $token): Response
    {
        $shareLink = InvoiceShareLink::query()->where('token', $token)->firstOrFail();
        abort_unless($shareLink->isUsable(), 404);

        // Password-protected links must have been unlocked in this session.
        abort_if(
            $shareLink->password_hash && ! session("invoice_share_verified.{$token}"),
            403,
        );

        $shareLink->markDownloaded();

        return $this->render($shareLink->invoice, download: true);
    }

    protected function render(Invoice $invoice, bool $download = false): Response
    {
        $pdf = Pdf::loadView('pdf.invoice', $this->pdf->viewData($invoice))->setPaper('a4');

        $filename = "{$invoice->invoice_number}.pdf";

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }
}

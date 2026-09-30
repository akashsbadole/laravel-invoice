<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
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
        $invoice->load(['customer', 'salesperson', 'items.charges', 'template']);

        $template = $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id);

        $token = $invoice->shareLinks()->where('is_active', true)->latest()->value('token');
        $publicUrl = $token ? route('invoices.public.show', $token) : null;

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'business' => BusinessSetting::forTenant($invoice->tenant_id),
            'template' => $template,
            'publicUrl' => $publicUrl,
            'qrSvg' => $publicUrl && $template->config('show_qr_code') ? $this->qrSvg($publicUrl) : null,
        ])->setPaper('a4');

        $filename = "{$invoice->invoice_number}.pdf";

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * QR is a progressive enhancement: if simplesoftwareio/simple-qrcode
     * isn't installed, the PDF simply falls back to the printed link.
     */
    protected function qrSvg(string $url): ?string
    {
        $facade = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';

        if (! class_exists($facade)) {
            return null;
        }

        try {
            $svg = $facade::size(90)->margin(0)->generate($url);

            return 'data:image/svg+xml;base64,'.base64_encode((string) $svg);
        } catch (\Throwable) {
            return null;
        }
    }
}

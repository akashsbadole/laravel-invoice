<?php

namespace App\Http\Controllers;

use App\Concerns\TenantScope;
use App\Enums\InvoiceEventType;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceShareLink;
use App\Models\Tenant;
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
        $shareLink = InvoiceShareLink::query()->withoutGlobalScope(TenantScope::class)->where('token', $token)->firstOrFail();
        abort_unless($shareLink->isUsable(), 404);

        // Password-protected links must have been unlocked in this session.
        abort_if(
            $shareLink->password_hash && ! session("invoice_share_verified.{$token}"),
            403,
        );

        return Tenant::runInContext($shareLink->tenant_id, function () use ($shareLink) {
            $firstDownload = $shareLink->downloaded_at === null;
            $shareLink->markDownloaded();

            if ($firstDownload) {
                InvoiceEvent::log(
                    $shareLink->invoice()->with('customer')->firstOrFail(),
                    InvoiceEventType::LinkDownloaded,
                    ['action' => 'link_downloaded', 'token' => $shareLink->token]
                );
            }

            return $this->render($shareLink->invoice()->with('customer')->firstOrFail(), download: true);
        });
    }

    protected function render(Invoice $invoice, bool $download = false): Response
    {
        $pdf = Pdf::loadView($this->pdf->viewName($invoice), $this->pdf->viewData($invoice))->setPaper('a4');

        $filename = "{$invoice->invoice_number}.pdf";

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }
}

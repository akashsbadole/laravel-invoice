<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Support\Industry;

/**
 * Single source of truth for the `pdf.invoice` view data.
 *
 * Every path that produces an invoice PDF — the staff stream, the public
 * share link, the customer portal and the email attachment — has to supply
 * the same variables, because the template reads the industry flags
 * unconditionally. Assembling them in one place keeps a new call site from
 * silently shipping a 500.
 */
class InvoicePdfService
{
    /**
     * @return array<string, mixed>
     */
    public function viewData(Invoice $invoice, bool $withShareLink = true): array
    {
        $invoice->loadMissing(['customer', 'salesperson', 'items.charges', 'template']);

        $business = BusinessSetting::forTenant($invoice->tenant_id);
        $template = $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id);
        $industry = $business->industryKey();

        // The portal and the email attachment deliberately omit the public
        // link so a customer document cannot be turned into a share token.
        $token = $withShareLink
            ? $invoice->shareLinks()->where('is_active', true)->latest()->value('token')
            : null;

        $publicUrl = $token !== null ? route('invoices.public.show', $token) : null;

        return [
            'invoice' => $invoice,
            'business' => $business,
            'template' => $template,
            'industry' => $industry,
            'showWeights' => Industry::usesWeightFields($industry),
            'showStones' => Industry::usesStoneFields($industry),
            'hasAreaItems' => $invoice->items->contains(
                fn ($item) => $item->length !== null && $item->width !== null
            ),
            'publicUrl' => $publicUrl,
            'qrSvg' => $publicUrl !== null && $template->config('show_qr_code')
                ? $this->qrSvg($publicUrl)
                : null,
        ];
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

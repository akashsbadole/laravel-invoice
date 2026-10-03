<?php

namespace App\Services;

use App\Enums\LineType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Support\Industry;
use App\Support\NumberToWords;

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
     * Which template renders this document.
     *
     * A quotation and a tax invoice are different documents with different
     * legal shapes, not one layout with a different title — a quote carries
     * a validity window and an acceptance block, while an invoice carries
     * PAN/GSTIN, HSN, place of supply and amount in words. Every delivery
     * path asks here so the emailed attachment can never disagree with the
     * page the staff previewed.
     */
    public function viewName(Invoice $invoice): string
    {
        return $invoice->document_type?->isQuotation()
            ? 'pdf.quotation'
            : 'pdf.invoice';
    }

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

            // Indian tax-invoice furniture. Each of these is derived from
            // data already stored, never invented: a missing transport
            // number must print blank rather than show a made-up one.
            'pan' => $this->pan($business->tax_number),
            'gstin' => $business->tax_number,
            'placeOfSupply' => $invoice->customer->state_code ?: $business->state_code,
            'hsnSummary' => $this->hsnSummary($invoice),
            'totalInWords' => NumberToWords::rupees((float) $invoice->grand_total),
            'taxInWords' => NumberToWords::rupees((float) $invoice->tax),
        ];
    }

    /**
     * A GSTIN's first ten characters are the PAN, so no separate field is
     * needed to print one.
     */
    protected function pan(?string $gstin): ?string
    {
        if (! $gstin || strlen($gstin) < 10) {
            return null;
        }

        return strtoupper(substr($gstin, 2, 10));
    }

    /**
     * HSN-wise taxable value and tax, as a GST return needs it shown.
     *
     * @return list<array{hsn:string,taxable:float,rate:float,cgst:float,sgst:float,igst:float,tax:float}>
     */
    protected function hsnSummary(Invoice $invoice): array
    {
        $rows = [];

        foreach ($invoice->items as $item) {
            // An exchange-credit line is the customer's own metal handed
            // back, so it is never a supply and never appears in the return.
            if ($item->line_type === LineType::ExchangeCredit) {
                continue;
            }

            $rate = (float) $item->tax_rate;
            $hsn = (string) ($item->hsn_code ?: '—');
            $key = $hsn.'|'.$rate;

            // Taxable value is the line's value after its own discount and
            // before tax, which is what the return reports.
            $taxable = max((float) $item->base_value - (float) $item->discount, 0);
            $tax = (float) $item->tax;

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'hsn' => $hsn,
                    'taxable' => 0.0,
                    'rate' => $rate,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                    'igst' => 0.0,
                    'tax' => 0.0,
                ];
            }

            $rows[$key]['taxable'] += $taxable;
            $rows[$key]['tax'] += $tax;

            // Split or single, decided by the rate itself: half of a rate
            // that is a whole number under 100 is intrastate, matching how
            // tax_mode renders CGST+SGST against IGST.
            if ($rate > 0 && fmod($rate, 1) === 0.0 && $rate < 100) {
                $rows[$key]['cgst'] += $tax / 2;
                $rows[$key]['sgst'] += $tax / 2;
            } else {
                $rows[$key]['igst'] += $tax;
            }
        }

        return array_values($rows);
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

<?php

namespace App\Services;

use App\Enums\InvoiceEventType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EInvoiceService
{
    /**
     * Build the e-invoice (Version 1.1) payload for an invoice.
     *
     * @return array{
     *     Version: string,
     *     TranDtls: array{TaxSch: string, SupTyp: string},
     *     DocDtls: array{Typ: string, No: string, Dt: string},
     *     SellerDtls: array{Gstin: ?string, LglNm: string, Addr1: ?string, Loc: ?string, Pin: ?string, Stcd: ?string},
     *     BuyerDtls: array{Gstin: ?string, LglNm: string, Addr1: ?string, Pos: ?string},
     *     ItemList: list<array{SlNo: string, PrdDesc: string, HsnCd: ?string, Qty: float, UnitPrice: float, TotAmt: float, TaxAmt: float, GstRt: float}>,
     *     ValDtls: array{AssVal: float, TotInvVal: float}
     * }
     */
    public function buildPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'items']);

        $business = BusinessSetting::forTenant($invoice->tenant_id);

        return [
            'Version' => '1.1',
            'TranDtls' => [
                'TaxSch' => 'GST',
                'SupTyp' => 'B2B',
            ],
            'DocDtls' => [
                'Typ' => $invoice->document_type->value === 'delivery_challan' ? 'CHL' : 'INV',
                'No' => $invoice->invoice_number,
                'Dt' => $invoice->invoice_date->format('d/m/Y'),
            ],
            'SellerDtls' => [
                'Gstin' => $business->tax_number,
                'LglNm' => $business->business_name,
                'Addr1' => $business->address,
                'Loc' => $business->address,
                'Pin' => $business->pincode,
                'Stcd' => $business->state_code,
            ],
            'BuyerDtls' => [
                'Gstin' => $invoice->customer->tax_number,
                'LglNm' => $invoice->customer->full_name,
                'Addr1' => $invoice->customer->address,
                'Pos' => $invoice->customer->state_code,
            ],
            'ItemList' => $invoice->items->map(fn ($item, $i) => [
                'SlNo' => (string) ($i + 1),
                'PrdDesc' => $item->item_name,
                'HsnCd' => $item->hsn_code,
                'Qty' => (float) $item->quantity,
                'UnitPrice' => (float) $item->rate,
                'TotAmt' => (float) $item->total - (float) $item->tax,
                'TaxAmt' => (float) $item->tax,
                'GstRt' => (float) $item->tax_rate,
            ])->all(),
            'ValDtls' => [
                'AssVal' => round((float) $invoice->grand_total - (float) $invoice->tax, 2),
                'TotInvVal' => (float) $invoice->grand_total,
            ],
        ];
    }

    /**
     * Generate an IRN. With the default `log` driver the payload is recorded
     * for inspection and the invoice is flagged pending — wire a real IRP
     * (Masters India / ClearTax / NIC sandbox) via EINVOICE_API_URL/KEY.
     */
    public function generate(Invoice $invoice, int $causedBy): Invoice
    {
        $driver = (string) config('services.einvoice.driver', 'log');
        $payload = $this->buildPayload($invoice);

        if ($driver !== 'api') {
            Log::info('[e-invoice:log] payload for '.$invoice->invoice_number, $payload);
            $invoice->update(['einvoice_status' => 'pending']);

            return $invoice;
        }

        $url = (string) config('services.einvoice.api_url');
        $key = (string) config('services.einvoice.api_key');

        if (! $url || ! $key) {
            throw new RuntimeException('E-invoice API is not configured (EINVOICE_API_URL / EINVOICE_API_KEY).');
        }

        try {
            $response = Http::withToken($key)->post($url, $payload)->throw()->json();
        } catch (Throwable $e) {
            $invoice->update(['einvoice_status' => 'failed']);

            throw $e;
        }

        $invoice->update([
            'einvoice_status' => 'generated',
            'irn' => $response['Irn'] ?? null,
            'irn_ack_no' => $response['AckNo'] ?? null,
            'irn_ack_date' => isset($response['AckDt']) ? Carbon::parse($response['AckDt']) : now(),
            'eway_bill_no' => $response['EwbNo'] ?? $invoice->eway_bill_no,
        ]);

        InvoiceEvent::log($invoice, InvoiceEventType::Sent, ['action' => 'einvoice_generated'], $causedBy);

        return $invoice;
    }
}

<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\LineType;
use App\Enums\TaxMode;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * GST e-invoice (IRN) generation.
 *
 * The registry is the e-invoice v1.1 schema. Providers differ in endpoint and
 * auth, so the transport is swappable:
 *   - driver "log" records the payload and flags the invoice pending;
 *   - driver "api" POSTs to EINVOICE_API_URL with EINVOICE_API_KEY and stores
 *     the returned IRN/ack/eway bill.
 */
class EInvoiceService
{
    public const DRIVER_LOG = 'log';

    public const DRIVER_API = 'api';

    /**
     * Reasons an invoice cannot be sent to the IRP, keyed by the field the
     * UI should point at.
     *
     * @return array<string, string>
     */
    public function blockingIssues(Invoice $invoice): array
    {
        $business = BusinessSetting::forTenant($invoice->tenant_id);
        $customer = $invoice->customer;
        $issues = [];

        if ($invoice->document_type->isQuotation()) {
            $issues['document_type'] = 'Quotations are not e-invoices.';
        }

        if (! $business->tax_number) {
            $issues['seller_gstin'] = 'Add your GSTIN in Business settings before generating an IRN.';
        }

        if (! $business->state_code) {
            $issues['seller_state'] = 'Add your state code in Business settings.';
        }

        if (! $business->address) {
            $issues['seller_address'] = 'Add your registered address in Business settings.';
        }

        if (! $customer) {
            $issues['customer'] = 'This invoice has no customer.';
        }

        $requiresGstinBuyer = $this->supplyType($invoice, $customer) === 'B2B';

        if ($requiresGstinBuyer && ! $customer?->tax_number) {
            $issues['buyer_gstin'] = 'A B2B e-invoice needs the customer\'s GSTIN.';
        }

        foreach ($invoice->items as $index => $item) {
            if (! $item->hsn_code) {
                $issues["items.{$index}.hsn_code"] = 'HSN/SAC is required on every line for an IRN.';
            }
        }

        if ((float) $invoice->grand_total <= 0) {
            $issues['grand_total'] = 'An e-invoice must have a positive total.';
        }

        return $issues;
    }

    /**
     * Build the e-invoice (Version 1.1) payload for an invoice.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'items']);
        $business = BusinessSetting::forTenant($invoice->tenant_id);
        $customer = $invoice->customer;

        // Exchange credit is the customer's own metal handed back — it is not
        // a supply, so it must never reach the IRP as a billed line.
        $lines = $invoice->items->reject(
            fn ($item) => $item->line_type === LineType::ExchangeCredit
        );

        $supTyp = $this->supplyType($invoice, $customer);

        return [
            'Version' => '1.1',
            'TranDtls' => [
                'TaxSch' => 'GST',
                'SupTyp' => $supTyp,
            ],
            'DocDtls' => [
                'Typ' => $invoice->document_type === DocumentType::DeliveryChallan ? 'CHL' : 'INV',
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
            'BuyerDtls' => $this->buyerDetails($invoice, $customer, $supTyp),
            'ItemList' => $lines
                ->values()
                ->map(fn ($item, $i) => $this->itemLine($item, $i + 1, $invoice->tax_mode))
                ->all(),
            'ValDtls' => $this->valueBreakdown($invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function supplyType(Invoice $invoice, ?Customer $customer): string
    {
        // An explicit gstin_type decides this. Without one, fall back to whether
        // the customer actually holds a GSTIN, which is the historical
        // behaviour.
        return $customer?->isBusinessBuyer() ? 'B2B' : 'B2C';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buyerDetails(Invoice $invoice, ?Customer $customer, string $supTyp): array
    {
        $business = BusinessSetting::forTenant($invoice->tenant_id);

        // B2C has no registered buyer, so the place of supply is the seller's
        // state; B2B follows the buyer's. A customer can override their home
        // state with an explicit place_of_supply, because goods are frequently
        // delivered somewhere other than where they are registered.
        $state = $supTyp === 'B2B'
            ? $customer?->effectivePlaceOfSupply()
            : $business->state_code;

        return [
            'Gstin' => $supTyp === 'B2B' ? $customer?->tax_number : null,
            'LglNm' => $customer?->full_name,
            'Addr1' => $customer?->address,
            'Pin' => null,
            'Stcd' => $state,
            'Pos' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemLine(object $item, int $slNo, TaxMode $taxMode): array
    {
        $taxableValue = (float) $item->total - (float) $item->tax;
        $intraState = $taxMode === TaxMode::CgstSgst;
        $tax = round((float) $item->tax, 2);
        $half = round($tax / 2, 2);

        return [
            'SlNo' => (string) $slNo,
            'PrdDesc' => $item->item_name,
            'HsnCd' => $item->hsn_code,
            'Qty' => (float) $item->quantity,
            'FreeQty' => 0,
            'Unit' => $this->unitOfMeasure($item->rate_type->value),
            'UnitPrice' => round((float) $item->rate, 3),
            'TotAmt' => round($taxableValue, 2),
            'AssAmt' => round($taxableValue, 2),
            'TaxAmt' => $tax,
            'GstRt' => (float) $item->tax_rate,
            'CgstAmt' => $intraState ? $half : 0,
            'SgstAmt' => $intraState ? $half : 0,
            'IgstAmt' => $intraState ? 0 : $tax,
        ];
    }

    protected function unitOfMeasure(string $rateType): string
    {
        return match ($rateType) {
            'per_gram' => 'GMS',
            'per_carat' => 'CTS',
            'per_kg' => 'KGS',
            'per_meter' => 'MTR',
            'per_sqft' => 'SQF',
            'per_sqm' => 'SQM',
            'per_box' => 'BOX',
            default => 'NOS',
        };
    }

    /**
     * IRN totals are derived, never trusted from the client: the taxable
     * value is the invoice total less tax, and the CGST/SGST/IGST columns
     * must sum back to that tax.
     *
     * @return array<string, mixed>
     */
    protected function valueBreakdown(Invoice $invoice): array
    {
        $tax = round((float) $invoice->tax, 2);
        $assessable = round((float) $invoice->grand_total - $tax, 2);

        $intraState = $invoice->tax_mode === TaxMode::CgstSgst;
        $half = round($tax / 2, 2);

        return [
            'AssVal' => $assessable,
            'ExpVal' => 0,
            'Discount' => 0,
            'TaxAmt' => $tax,
            'CgstAmt' => $intraState ? $half : 0,
            'SgstAmt' => $intraState ? $half : 0,
            'IgstAmt' => $intraState ? 0 : $tax,
            'TotInvVal' => round((float) $invoice->grand_total, 2),
            'RoundOff' => round((float) $invoice->round_off, 2),
        ];
    }

    /**
     * Generate an IRN. With the default `log` driver the payload is recorded
     * for inspection and the invoice is flagged pending — wire a real IRP
     * (Masters India / ClearTax / NIC sandbox) via EINVOICE_API_URL/KEY.
     */
    public function generate(Invoice $invoice, int $causedBy): Invoice
    {
        if ($invoice->irn !== null) {
            throw new RuntimeException('This invoice already has an IRN. A registered IRN cannot be cancelled.');
        }

        if ($issues = $this->blockingIssues($invoice)) {
            throw new RuntimeException(collect($issues)->first());
        }

        $driver = (string) config('services.einvoice.driver', self::DRIVER_LOG);
        $payload = $this->buildPayload($invoice);

        if ($driver !== self::DRIVER_API) {
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

        InvoiceEvent::log(
            $invoice,
            InvoiceEventType::Sent,
            ['action' => 'einvoice_generated', 'irn' => $response['Irn'] ?? null],
            $causedBy,
        );

        return $invoice;
    }
}

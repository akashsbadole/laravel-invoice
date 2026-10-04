<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Same thing again" — the customer's repeat order.
 *
 * A jewellery customer returning for the same piece is the single most
 * common thing that happens in the shop, and today it means retyping the
 * whole quote. This rebuilds a draft quotation from a past document with
 * every metal line re-priced at *today's* rate.
 *
 * Re-pricing is the entire point. Copying the stored rate verbatim would
 * quote the customer yesterday's gold, which is either a loss to the shop
 * or a conversation nobody wants to have at the counter.
 */
class ReQuoteService
{
    public function __construct(private readonly InvoiceCalculationService $calculator) {}

    /**
     * A fresh draft quotation for the same items, at today's metal rates.
     *
     * Non-metal lines (a fixed-price repair, a per-piece item) keep their
     * original rate, because there is no daily rate to move them to.
     */
    public function create(Invoice $source, User $user): Invoice
    {
        $source->loadMissing(['customer', 'items.charges', 'charges', 'salesperson']);

        return DB::transaction(function () use ($source, $user) {
            $business = BusinessSetting::query()->lockForUpdate()->first() ?? BusinessSetting::current();
            $customer = Customer::query()->findOrFail($source->customer_id);

            $rates = $this->todayRates();
            $input = $this->buildInput($source, $rates, $business);

            $computed = $this->calculator->calculate([
                ...$input,
                'rounding_mode' => $business->rounding_mode,
                'group_discount_percent' => 0,
            ]);

            $quotation = Invoice::create([
                'customer_id' => $source->customer_id,
                'document_type' => DocumentType::Quotation->value,
                'status' => InvoiceStatus::Draft,
                'invoice_number' => $business->nextQuotationNumber(),
                'invoice_date' => today()->toDateString(),
                'due_date' => $source->due_date,
                // A fresh quote needs a fresh validity window, not the
                // one the old quote happened to carry.
                'quotation_valid_until' => today()->addDays(14)->toDateString(),
                'reference_number' => $source->reference_number,
                'salesperson_id' => $source->salesperson_id,
                'invoice_template_id' => $source->invoice_template_id,
                'pricing_mode' => $source->pricing_mode,
                'tax_mode' => $computed['tax_mode'],
                'tax_breakdown' => $computed['tax_breakdown'],
                'subtotal' => $computed['subtotal'],
                'charges_summary' => $computed['charges_summary'],
                'discount' => $computed['discount'],
                'tax' => $computed['tax'],
                'tds_rate' => $computed['tds_rate'],
                'tds_amount' => $computed['tds_amount'],
                'tcs_rate' => $computed['tcs_rate'],
                'tcs_amount' => $computed['tcs_amount'],
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'paid_amount' => 0,
                // TDS is withheld at settlement, so it never inflates the
                // amount the customer still owes.
                'balance_amount' => max((float) $computed['grand_total'] - (float) $computed['tds_amount'], 0),
                'notes' => $source->notes,
                'terms' => $source->terms,
                'attributes' => $source->attributes,
                'revision_number' => 1,
                'revision_note' => null,
                // Today's rate is what this quote is priced at, so the rate
                // date has to be today — not the date of the quote it copies.
                'rate_locked_at' => today()->toDateString(),
                'created_by' => $user->id,
            ]);

            $quotation->forceFill([
                'discount_approved_by' => null,
                'discount_approved_at' => null,
                'discount_approved_discount' => null,
            ])->save();

            $this->persist($quotation, $computed);

            InvoiceEvent::log($quotation, InvoiceEventType::Created, [
                're_quoted_from' => $source->invoice_number,
            ], $user->id);

            ActivityLog::record('invoice.created', $quotation, "Re-quoted {$source->invoice_number} as {$quotation->invoice_number}");

            return $quotation;
        });
    }

    /**
     * Today's rate for every metal + purity combination the shop records.
     *
     * @return array<string, array{rate: float, date: string}>
     */
    protected function todayRates(): array
    {
        return MetalRate::query()
            ->get(['metal_type', 'purity', 'rate_date', 'rate_per_gram'])
            ->groupBy(fn (MetalRate $r) => $this->rateKey($r->metal_type, $r->purity))
            ->map(fn ($rows, string $key) => [
                // groupBy preserves insertion order, so the newest rate for
                // a metal lands last and wins.
                'rate' => (float) $rows->last()->rate_per_gram,
                'date' => (string) $rows->last()->rate_date,
            ])
            ->all();
    }

    protected function rateKey(?string $metal, ?string $purity): string
    {
        return mb_strtolower(trim((string) $metal)).'|'.mb_strtolower(trim((string) $purity));
    }

    /**
     * Rebuild the calculator's input shape from the stored document.
     *
     * @param  array<string, array{rate: float, date: string}>  $rates
     * @return array<string, mixed>
     */
    protected function buildInput(Invoice $source, array $rates, BusinessSetting $business): array
    {
        return [
            'document_type' => DocumentType::Quotation->value,
            // The calculator reads raw values, so these must be uncast —
            // the model hands back enum instances.
            'pricing_mode' => $source->pricing_mode->value,
            'tax_mode' => $source->tax_mode->value,
            'tax_rate' => 0,
            'discount' => $source->discount,
            'tcs_rate' => $source->tcs_rate,
            'tds_rate' => $source->tds_rate,
            'items' => $source->items->map(function ($item) use ($rates) {
                $key = $this->rateKey($item->metal_type, $item->purity);
                $today = $rates[$key] ?? null;
                $isPerGram = $item->rate_type?->value === 'per_gram';

                // Only a per-gram metal line follows the market. Anything
                // else keeps whatever price it was quoted at.
                $rate = ($isPerGram && $today)
                    ? $today['rate']
                    : (float) $item->rate;

                return [
                    'item_name' => $item->item_name,
                    'line_type' => $item->line_type->value,
                    'description' => $item->description,
                    'item_code' => $item->item_code,
                    'catalog_item_id' => $item->catalog_item_id,
                    'catalog_variant_id' => $item->catalog_variant_id,
                    'hsn_code' => $item->hsn_code,
                    'brand' => $item->brand,
                    'model_number' => $item->model_number,
                    'serial_number' => $item->serial_number,
                    'warranty_months' => $item->warranty_months,
                    'size_label' => $item->size_label,
                    'finish' => $item->finish,
                    'grade' => $item->grade,
                    'specification' => $item->specification,
                    'batch_number' => $item->batch_number,
                    'length' => $item->length,
                    'width' => $item->width,
                    'height' => $item->height,
                    'wastage_percent' => $item->wastage_percent,
                    'boxes' => $item->boxes,
                    'attributes' => $item->attributes ?? [],
                    'metal_type' => $item->metal_type,
                    'purity' => $item->purity,
                    'huid_number' => $item->huid_number,
                    'stone_clarity' => $item->stone_clarity,
                    'stone_color' => $item->stone_color,
                    'stone_carat' => (float) $item->stone_carat,
                    'certificate_number' => $item->certificate_number,
                    'quantity' => $item->quantity,
                    'gross_weight' => (float) $item->gross_weight,
                    'net_weight' => (float) $item->net_weight,
                    'stone_weight' => (float) $item->stone_weight,
                    'rate_type' => $item->rate_type->value,
                    'rate' => $rate,
                    'discount' => (float) $item->discount,
                    'tax_rate' => (float) $item->tax_rate,
                    'charges' => $item->charges->map(fn ($charge) => [
                        'charge_type_id' => $charge->charge_type_id,
                        // A percentage charge is a rate, not an amount, so it
                        // is carried across untouched.
                        'rate' => (float) $charge->rate,
                    ])->values()->all(),
                ];
            })->all(),
            'invoice_charges' => $source->charges->map(fn ($charge) => [
                'charge_type_id' => $charge->charge_type_id,
                'rate' => (float) $charge->rate,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    protected function persist(Invoice $invoice, array $computed): void
    {
        // Mirrors InvoiceController::persistItemsAndCharges() — the calculator
        // already returns rows shaped for InvoiceItem::create().
        foreach ($computed['items'] as $itemData) {
            $charges = $itemData['charges'];
            unset($itemData['charges'], $itemData['charges_total']);

            $item = $invoice->items()->create($itemData);

            foreach ($charges as $chargeData) {
                $item->charges()->create($chargeData);
            }
        }

        foreach ($computed['invoice_charges'] as $chargeData) {
            $invoice->charges()->create($chargeData);
        }
    }

    /**
     * Whether this document can be re-quoted — anything with no lines is
     * not worth repeating.
     */
    public function canReQuote(Invoice $invoice): bool
    {
        return $invoice->items->isNotEmpty()
            && ! in_array($invoice->document_type, DocumentType::adjustmentValues(), true);
    }
}

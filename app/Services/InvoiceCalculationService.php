<?php

namespace App\Services;

use App\Enums\ChargeAppliesTo;
use App\Enums\ChargeCalculationType;
use App\Enums\LineType;
use App\Enums\PricingMode;
use App\Enums\RateType;
use App\Enums\RoundingMode;
use App\Enums\TaxMode;
use App\Models\ChargeType;
use Illuminate\Support\Collection;

/**
 * Turns raw, untrusted invoice form input into fully-computed items,
 * item charges, invoice charges, tax breakdown and invoice totals.
 *
 * Nothing here trusts a total sent by the client. The TypeScript mirror
 * (resources/js/lib/invoice-calculations.ts) only powers the live preview.
 *
 * Displayed identity:
 *   subtotal + sum(charges_summary) + tax - discount + tcs + round_off = grand_total
 * where charges_summary includes a negative "Item discounts" row. TDS is
 * withheld later (it shrinks the balance, not the invoice total).
 */
class InvoiceCalculationService
{
    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function calculate(array $input): array
    {
        $pricingMode = PricingMode::from($input['pricing_mode'] ?? PricingMode::JewelryCalculated->value);
        $taxMode = TaxMode::tryFrom($input['tax_mode'] ?? '') ?? TaxMode::Single;
        $roundingMode = RoundingMode::tryFrom($input['rounding_mode'] ?? '')
            ?? RoundingMode::NearestRupee;
        $tcsRate = max((float) ($input['tcs_rate'] ?? 0), 0);
        $tdsRate = max((float) ($input['tds_rate'] ?? 0), 0);

        // Only load the charge catalogue when the submission actually
        // references charges — a charge-free invoice needs no query at all.
        $chargeTypes = $this->referencesCharges($input)
            ? ChargeType::query()->get()->keyBy('id')
            : new Collection;

        $items = [];
        $subtotal = 0.0;      // base + item charges - item discounts (base for % invoice charges)
        $baseSubtotal = 0.0;  // base value only (the displayed "Subtotal")
        $itemDiscounts = 0.0;
        $itemsTaxTotal = 0.0;
        $taxBySlab = [];

        foreach ($input['items'] ?? [] as $index => $itemInput) {
            $item = $this->calculateItem($itemInput, $pricingMode, $chargeTypes, $index);
            $items[] = $item;
            $subtotal += $item['base_value'] + $item['charges_total'] - $item['discount'];
            $baseSubtotal += $item['base_value'];
            $itemDiscounts += $item['discount'];
            $itemsTaxTotal += $item['tax'];
            $this->addToSlab($taxBySlab, $item['tax_rate'], $item['tax']);
        }

        $subtotal = round($subtotal, 2);

        $invoiceCharges = [];
        $invoiceChargesTotal = 0.0;
        $invoiceChargesTaxable = 0.0;

        foreach ($input['invoice_charges'] ?? [] as $chargeInput) {
            $charge = $this->calculateInvoiceCharge($chargeInput, $chargeTypes, $subtotal);
            if ($charge === null) {
                continue;
            }
            $invoiceCharges[] = $charge;
            $invoiceChargesTotal += $charge['amount'];
            if ($charge['is_taxable']) {
                $invoiceChargesTaxable += $charge['amount'];
            }
        }

        $discount = round((float) ($input['discount'] ?? 0), 2);
        $invoiceLevelTaxRate = (float) ($input['tax_rate'] ?? 0);
        $invoiceLevelTax = round($invoiceChargesTaxable * ($invoiceLevelTaxRate / 100), 2);
        $this->addToSlab($taxBySlab, $invoiceLevelTaxRate, $invoiceLevelTax);

        $taxBreakdown = $this->buildTaxBreakdown($taxBySlab, $taxMode);
        $tax = $taxBreakdown !== []
            ? round(array_sum(array_column($taxBreakdown, 'amount')), 2)
            : round($itemsTaxTotal + $invoiceLevelTax, 2);

        $beforeTaxation = round($subtotal + $invoiceChargesTotal + $tax - $discount, 2);

        // TCS (collected at source) rides on top of the invoice value and is
        // part of what the customer is billed, so it lands before rounding.
        $tcsAmount = $tcsRate > 0 ? round($beforeTaxation * ($tcsRate / 100), 2) : 0.0;
        // TDS (deducted at source) is withheld from the payment, so it never
        // inflates the invoice — Invoice::recalculatePaymentStatus() subtracts
        // it from the balance instead.
        $tdsAmount = $tdsRate > 0 ? round($beforeTaxation * ($tdsRate / 100), 2) : 0.0;

        $beforeRounding = round($beforeTaxation + $tcsAmount, 2);
        // An exchange credit can outweigh the goods; the customer then owes
        // nothing (or is owed change) rather than a negative invoice.
        $grandTotal = max($roundingMode->apply($beforeRounding), 0.0);
        $roundOff = round($grandTotal - $beforeRounding, 2);

        return [
            'items' => $items,
            'invoice_charges' => $invoiceCharges,
            'subtotal' => round($baseSubtotal, 2),
            'discount' => $discount,
            'tax' => $tax,
            'tax_mode' => $taxMode->value,
            'tax_breakdown' => $taxBreakdown,
            'tds_rate' => $tdsRate,
            'tds_amount' => $tdsAmount,
            'tcs_rate' => $tcsRate,
            'tcs_amount' => $tcsAmount,
            'round_off' => $roundOff,
            'grand_total' => $grandTotal,
            'charges_summary' => $this->summarizeCharges($items, $invoiceCharges, round($itemDiscounts, 2)),
        ];
    }

    /**
     * @param  array<string,mixed>  $itemInput
     * @param  Collection<int,ChargeType>  $chargeTypes
     * @return array<string,mixed>
     */
    protected function calculateItem(array $itemInput, PricingMode $pricingMode, Collection $chargeTypes, int $sortOrder): array
    {
        $quantity = max((int) ($itemInput['quantity'] ?? 1), 1);
        $rate = (float) ($itemInput['rate'] ?? 0);
        $netWeight = (float) ($itemInput['net_weight'] ?? 0);
        $stoneCarat = (float) ($itemInput['stone_carat'] ?? 0);
        $rateType = RateType::from($itemInput['rate_type'] ?? RateType::PerGram->value);

        // Area-priced lines (tiles, marble, flooring) bill on length × width,
        // plus any wastage the trade adds on top.
        $area = $this->areaOf($itemInput, $rateType);
        $wastage = (float) ($itemInput['wastage_percent'] ?? 0);
        $billableArea = $wastage > 0 ? $area * (1 + ($wastage / 100)) : $area;
        $boxes = (float) ($itemInput['boxes'] ?? 0);
        $metres = (float) ($itemInput['length'] ?? 0);

        $lineType = LineType::tryFrom($itemInput['line_type'] ?? '') ?? LineType::Sale;

        $baseValuePerUnit = match (true) {
            // Jewelry weight/carat pricing historically honours the manual
            // pricing mode: the typed rate IS the amount per unit. Every other
            // trade bills from a measure (area, box, metre, kg), so those rate
            // types always multiply regardless of pricing mode.
            $rateType === RateType::PerGram,
            $rateType === RateType::PerCarat => $pricingMode === PricingMode::Manual
                ? $rate
                : ($rateType === RateType::PerGram ? $rate * $netWeight : $rate * $stoneCarat),
            $rateType === RateType::PerSqft => $rate * $billableArea,
            $rateType === RateType::PerSqm => $rate * $billableArea,
            $rateType === RateType::PerMeter => $rate * $metres,
            $rateType === RateType::PerKg => $rate * $netWeight,
            $rateType === RateType::PerBox => $rate * max($boxes, 1),
            // Paint, adhesives and other liquids bill by volume: the line
            // quantity IS the number of litres, so the plain rate × quantity
            // applied when the total is computed is the whole calculation.
            // (Multiplying by quantity here as well would square it.)
            $rateType === RateType::PerLitre => $rate,
            default => $rate,
        };

        $charges = [];
        $chargesPerUnit = 0.0;
        $taxableBasePerUnit = $baseValuePerUnit;

        if ($pricingMode === PricingMode::JewelryCalculated && $lineType->supportsCharges()) {
            foreach ($itemInput['charges'] ?? [] as $chargeInput) {
                $charge = $this->calculateItemCharge($chargeInput, $chargeTypes, $baseValuePerUnit, $netWeight, $stoneCarat);
                if ($charge === null) {
                    continue;
                }
                $charges[] = $charge;
                $chargesPerUnit += $charge['amount'];
                if ($charge['is_taxable']) {
                    $taxableBasePerUnit += $charge['amount'];
                }
            }
        }

        $discount = $lineType->isTaxable() ? (float) ($itemInput['discount'] ?? 0) : 0.0;
        // Exchange credit is the customer's own metal handed back, not a
        // supply — charging GST on it would be wrong.
        $taxRate = $lineType->isTaxable() ? (float) ($itemInput['tax_rate'] ?? 0) : 0.0;

        $baseValue = round($baseValuePerUnit * $quantity, 2);

        if ($lineType === LineType::ExchangeCredit) {
            $baseValue = -abs($baseValue);
        }

        $chargesTotal = round($chargesPerUnit * $quantity, 2);
        $taxableAmount = round(($taxableBasePerUnit * $quantity) - $discount, 2);
        $tax = round(max($taxableAmount, 0) * ($taxRate / 100), 2);
        $total = round($baseValue + $chargesTotal - $discount + $tax, 2);

        // Reserved attribute keys are promoted into dedicated invoice columns.
        // A catalog can carry certification identifiers as free-form attributes;
        // the sold line should surface them in the HUID/Cert fields rather than
        // duplicating them in the printed attribute list. An explicit line
        // value always wins.
        $attributes = $this->normalizeAttributes($itemInput['attributes'] ?? null);
        $huidNumber = $this->promotedAttribute($itemInput, $attributes, 'huid_number');
        $certificateNumber = $this->promotedAttribute($itemInput, $attributes, 'certificate_number');

        return [
            'sort_order' => $sortOrder,
            'line_type' => $lineType->value,
            'item_name' => $itemInput['item_name'] ?? '',
            'description' => $itemInput['description'] ?? null,
            'item_code' => $itemInput['item_code'] ?? null,
            'catalog_item_id' => $itemInput['catalog_item_id'] ?? null,
            'catalog_variant_id' => isset($itemInput['catalog_variant_id']) && $itemInput['catalog_variant_id'] !== ''
                ? (int) $itemInput['catalog_variant_id']
                : null,
            'hsn_code' => $itemInput['hsn_code'] ?? null,
            'brand' => $itemInput['brand'] ?? null,
            'model_number' => $itemInput['model_number'] ?? null,
            'serial_number' => $itemInput['serial_number'] ?? null,
            'warranty_months' => isset($itemInput['warranty_months']) && $itemInput['warranty_months'] !== ''
                ? (int) $itemInput['warranty_months']
                : null,
            'size_label' => $itemInput['size_label'] ?? null,
            'finish' => $itemInput['finish'] ?? null,
            'grade' => $itemInput['grade'] ?? null,
            'specification' => $itemInput['specification'] ?? null,
            'batch_number' => $itemInput['batch_number'] ?? null,
            'length' => $this->nullableFloat($itemInput, 'length'),
            'width' => $this->nullableFloat($itemInput, 'width'),
            'height' => $this->nullableFloat($itemInput, 'height'),
            'wastage_percent' => $this->nullableFloat($itemInput, 'wastage_percent'),
            'boxes' => $this->nullableFloat($itemInput, 'boxes'),
            'attributes' => $attributes === [] ? null : $attributes,
            'metal_type' => $itemInput['metal_type'] ?? null,
            'purity' => $itemInput['purity'] ?? null,
            'huid_number' => $huidNumber,
            'stone_clarity' => $itemInput['stone_clarity'] ?? null,
            'stone_color' => $itemInput['stone_color'] ?? null,
            'stone_carat' => $stoneCarat,
            'certificate_number' => $certificateNumber,
            'quantity' => $quantity,
            'gross_weight' => (float) ($itemInput['gross_weight'] ?? 0),
            'net_weight' => $netWeight,
            'stone_weight' => (float) ($itemInput['stone_weight'] ?? 0),
            'rate_type' => $rateType->value,
            'rate' => $rate,
            'base_value' => $baseValue,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'total' => $total,
            'charges' => $charges,
            'charges_total' => $chargesTotal,
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    protected function referencesCharges(array $input): bool
    {
        if (($input['invoice_charges'] ?? []) !== []) {
            return true;
        }

        foreach ($input['items'] ?? [] as $item) {
            if (($item['charges'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Billable surface area for area-priced lines.
     *
     * Sq ft converts from cm dimensions (the usual way a tiles shop measures),
     * so 100cm × 100cm = 1 sq ft. Sq m uses metres directly.
     *
     * @param  array<string,mixed>  $itemInput
     */
    protected function areaOf(array $itemInput, RateType $rateType): float
    {
        if (! $rateType->isAreaBased()) {
            return 0.0;
        }

        $length = (float) ($itemInput['length'] ?? 0);
        $width = (float) ($itemInput['width'] ?? 0);

        // No dimensions entered yet: the caller must still bill something
        // sensible, so fall back to the rate as a flat per-unit amount
        // rather than silently zeroing the line.
        if ($length <= 0 || $width <= 0) {
            return 1.0;
        }

        return $rateType === RateType::PerSqft
            ? ($length * $width) / 929.0304
            : ($length / 100) * ($width / 100);
    }

    /**
     * Absent, null and empty-string all mean "not recorded" for the nullable
     * numeric item fields.
     *
     * @param  array<string,mixed>  $itemInput
     */
    protected function nullableFloat(array $itemInput, string $key): ?float
    {
        $value = $itemInput[$key] ?? null;

        return ($value === null || $value === '') ? null : (float) $value;
    }

    /**
     * Promote a reserved free-form attribute into a dedicated line column.
     *
     * The key is always removed from the attribute map so the PDF does not
     * print the same identifier twice.
     */
    protected function promotedAttribute(array $itemInput, ?array &$attributes, string $key): ?string
    {
        $explicit = trim((string) ($itemInput[$key] ?? ''));
        $promoted = null;

        if (is_array($attributes) && array_key_exists($key, $attributes)) {
            $promoted = trim((string) $attributes[$key]);
            unset($attributes[$key]);
        }

        if ($explicit !== '') {
            return mb_substr($explicit, 0, 255);
        }

        return $promoted === '' || $promoted === null ? null : mb_substr($promoted, 0, 255);
    }

    /**
     * Free-form item attributes arrive as either a JSON string or an array
     * depending on how the client sent them; store a clean key => value map.
     *
     * @return array<string,string>|null
     */
    protected function normalizeAttributes(mixed $attributes): ?array
    {
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }

        if (! is_array($attributes) || $attributes === []) {
            return null;
        }

        $clean = [];

        foreach ($attributes as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $stringValue = is_scalar($value) ? (string) $value : (json_encode($value) ?: '');

            if (trim($stringValue) === '') {
                continue;
            }

            $clean[mb_substr($key, 0, 50)] = mb_substr(trim($stringValue), 0, 255);
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  array<string,mixed>  $chargeInput
     * @param  Collection<int,ChargeType>  $chargeTypes
     * @return array<string,mixed>|null
     */
    protected function calculateItemCharge(array $chargeInput, Collection $chargeTypes, float $baseValuePerUnit, float $netWeight, float $stoneCarat): ?array
    {
        $chargeType = $chargeTypes->get((int) ($chargeInput['charge_type_id'] ?? 0));

        if (! $chargeType || $chargeType->applies_to !== ChargeAppliesTo::Item) {
            return null;
        }

        $rate = (float) ($chargeInput['rate'] ?? $chargeType->default_rate ?? 0);

        $amount = match ($chargeType->calculation_type) {
            ChargeCalculationType::Fixed => $rate,
            ChargeCalculationType::Percentage => $baseValuePerUnit * ($rate / 100),
            ChargeCalculationType::PerGram => $rate * $netWeight,
            ChargeCalculationType::PerCarat => $rate * $stoneCarat,
        };

        return $this->chargeRow($chargeType, $rate, $amount);
    }

    /**
     * @param  array<string,mixed>  $chargeInput
     * @param  Collection<int,ChargeType>  $chargeTypes
     * @return array<string,mixed>|null
     */
    protected function calculateInvoiceCharge(array $chargeInput, Collection $chargeTypes, float $subtotal): ?array
    {
        $chargeType = $chargeTypes->get((int) ($chargeInput['charge_type_id'] ?? 0));

        if (! $chargeType || $chargeType->applies_to !== ChargeAppliesTo::Invoice) {
            return null;
        }

        $rate = (float) ($chargeInput['rate'] ?? $chargeType->default_rate ?? 0);

        $amount = $chargeType->calculation_type === ChargeCalculationType::Percentage
            ? max($subtotal, 0.0) * ($rate / 100)
            : $rate;

        return $this->chargeRow($chargeType, $rate, $amount);
    }

    /**
     * @return array<string,mixed>
     */
    protected function chargeRow(ChargeType $chargeType, float $rate, float $amount): array
    {
        return [
            'charge_type_id' => $chargeType->id,
            'label' => $chargeType->name,
            'code' => $chargeType->code,
            'calculation_type' => $chargeType->calculation_type->value,
            'is_taxable' => $chargeType->is_taxable,
            'rate' => round($rate, 2),
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @param  array<string,float>  $taxBySlab
     */
    protected function addToSlab(array &$taxBySlab, float $rate, float $amount): void
    {
        if ($amount == 0.0) {
            return;
        }
        $key = number_format($rate, 2, '.', '');
        $taxBySlab[$key] = ($taxBySlab[$key] ?? 0.0) + $amount;
    }

    /**
     * GST presentation: one row pair per rate slab (e.g. CGST @1.5% + SGST @1.5%
     * for a 3% slab). Single mode returns [] and the invoice shows one tax line.
     *
     * @param  array<string,float>  $taxBySlab
     * @return array<int,array{label:string,rate:float,amount:float}>
     */
    protected function buildTaxBreakdown(array $taxBySlab, TaxMode $mode): array
    {
        if ($mode === TaxMode::Single) {
            return [];
        }

        ksort($taxBySlab, SORT_NUMERIC);
        $rows = [];

        foreach ($taxBySlab as $rateKey => $amount) {
            $rate = (float) $rateKey;
            $amount = round($amount, 2);

            if ($mode === TaxMode::Igst) {
                $rows[] = ['label' => 'IGST @ '.$this->fmtRate($rate).'%', 'rate' => $rate, 'amount' => $amount];

                continue;
            }

            $half = round($amount / 2, 2);
            $halfRate = $rate / 2;
            $rows[] = ['label' => 'CGST @ '.$this->fmtRate($halfRate).'%', 'rate' => $halfRate, 'amount' => $half];
            $rows[] = ['label' => 'SGST @ '.$this->fmtRate($halfRate).'%', 'rate' => $halfRate, 'amount' => round($amount - $half, 2)];
        }

        return $rows;
    }

    protected function fmtRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<int,array<string,mixed>>  $invoiceCharges
     * @return array<int,array<string,mixed>>
     */
    protected function summarizeCharges(array $items, array $invoiceCharges, float $itemDiscounts): array
    {
        $totals = [];

        foreach ($items as $item) {
            foreach ($item['charges'] as $charge) {
                $key = $charge['code'] ?? $charge['label'];
                $totals[$key]['label'] ??= $charge['label'];
                $totals[$key]['amount'] = ($totals[$key]['amount'] ?? 0) + ($charge['amount'] * $item['quantity']);
            }
        }

        foreach ($invoiceCharges as $charge) {
            $key = $charge['code'] ?? $charge['label'];
            $totals[$key]['label'] ??= $charge['label'];
            $totals[$key]['amount'] = ($totals[$key]['amount'] ?? 0) + $charge['amount'];
        }

        if ($itemDiscounts > 0) {
            $totals['item_discount'] = ['label' => 'Item discounts', 'amount' => -$itemDiscounts];
        }

        $rows = [];
        foreach ($totals as $code => $row) {
            $rows[] = ['code' => $code, 'label' => $row['label'], 'amount' => round($row['amount'], 2)];
        }

        return $rows;
    }
}

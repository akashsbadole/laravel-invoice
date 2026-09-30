<?php

namespace App\Services;

use App\Enums\ChargeAppliesTo;
use App\Enums\ChargeCalculationType;
use App\Enums\PricingMode;
use App\Enums\RateType;
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
 *   subtotal + sum(charges_summary) + tax - discount + round_off = grand_total
 * where charges_summary includes a negative "Item discounts" row.
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
        $chargeTypes = ChargeType::query()->get()->keyBy('id');

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

        $beforeRounding = round($subtotal + $invoiceChargesTotal + $tax - $discount, 2);
        $grandTotal = round($beforeRounding, 0);
        $roundOff = round($grandTotal - $beforeRounding, 2);

        return [
            'items' => $items,
            'invoice_charges' => $invoiceCharges,
            'subtotal' => round($baseSubtotal, 2),
            'discount' => $discount,
            'tax' => $tax,
            'tax_mode' => $taxMode->value,
            'tax_breakdown' => $taxBreakdown,
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

        $baseValuePerUnit = match (true) {
            $pricingMode === PricingMode::Manual => $rate,
            $rateType === RateType::PerGram => $rate * $netWeight,
            $rateType === RateType::PerCarat => $rate * $stoneCarat,
            default => $rate,
        };

        $charges = [];
        $chargesPerUnit = 0.0;
        $taxableBasePerUnit = $baseValuePerUnit;

        if ($pricingMode === PricingMode::JewelryCalculated) {
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

        $discount = (float) ($itemInput['discount'] ?? 0);
        $taxRate = (float) ($itemInput['tax_rate'] ?? 0);

        $baseValue = round($baseValuePerUnit * $quantity, 2);
        $chargesTotal = round($chargesPerUnit * $quantity, 2);
        $taxableAmount = round(($taxableBasePerUnit * $quantity) - $discount, 2);
        $tax = round(max($taxableAmount, 0) * ($taxRate / 100), 2);
        $total = round($baseValue + $chargesTotal - $discount + $tax, 2);

        return [
            'sort_order' => $sortOrder,
            'item_name' => $itemInput['item_name'] ?? '',
            'description' => $itemInput['description'] ?? null,
            'item_code' => $itemInput['item_code'] ?? null,
            'hsn_code' => $itemInput['hsn_code'] ?? null,
            'metal_type' => $itemInput['metal_type'] ?? null,
            'purity' => $itemInput['purity'] ?? null,
            'huid_number' => $itemInput['huid_number'] ?? null,
            'stone_clarity' => $itemInput['stone_clarity'] ?? null,
            'stone_color' => $itemInput['stone_color'] ?? null,
            'stone_carat' => $stoneCarat,
            'certificate_number' => $itemInput['certificate_number'] ?? null,
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
            ? $subtotal * ($rate / 100)
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

<?php

namespace App\Services;

use App\Enums\InvoiceEventType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;

/**
 * One rule, not a rule engine: a discount above the configured share of
 * the bill has to be explicitly approved before the money moves
 * (quotation → invoice). The approval is snapshotted — it covers exactly
 * the discount it was granted for, so quietly raising the discount
 * afterwards requires a fresh approval.
 */
class DiscountApprovalService
{
    public function threshold(): ?float
    {
        $setting = BusinessSetting::current()->discount_approval_threshold;

        return $setting === null ? null : (float) $setting;
    }

    /**
     * Discount as a share of the original bill, before the discount was
     * taken off. Grand total + discount = what the customer would have
     * paid with no discount, so this is the share the shop gave away.
     */
    public function discountPercent(Invoice $invoice): float
    {
        $discount = (float) $invoice->discount;
        $gross = (float) $invoice->grand_total + $discount;

        return $gross > 0 ? round(($discount / $gross) * 100, 2) : 0.0;
    }

    /**
     * Whether this document needs an owner's approval before it can
     * convert to an invoice. True only when a threshold is configured,
     * the discount is over it, and no approval already covers at least
     * the current discount amount.
     */
    public function required(Invoice $invoice): bool
    {
        $threshold = $this->threshold();

        if ($threshold === null || $threshold <= 0) {
            return false;
        }

        if ($this->discountPercent($invoice) <= $threshold) {
            return false;
        }

        return $invoice->discount_approved_discount === null
            || (float) $invoice->discount_approved_discount < (float) $invoice->discount;
    }

    /**
     * Stamp this invoice's discount as approved, pinning exactly which
     * amount was covered.
     */
    public function approve(Invoice $invoice, User $by): void
    {
        $invoice->update([
            'discount_approved_by' => $by->id,
            'discount_approved_at' => now(),
            'discount_approved_discount' => $invoice->discount,
        ]);

        InvoiceEvent::log($invoice, InvoiceEventType::Updated, [
            'action' => 'discount_approved',
            'discount' => $invoice->discount,
            'percent' => $this->discountPercent($invoice),
        ], $by->id);
    }
}

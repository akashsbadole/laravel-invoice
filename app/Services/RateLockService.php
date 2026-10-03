<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * The metal rate a quotation was priced at.
 *
 * A quotation is a promise: the customer accepts a number, and the
 * bill has to land on that number even when the daily rate moves
 * between acceptance and billing. The date the rate was struck is
 * the evidence — it is printed on the quote, the WhatsApp message
 * and the bill, and it is what tells staff a price has gone stale.
 */
class RateLockService
{
    /** Days after which a locked rate should be re-confirmed. */
    public const STALE_DAYS = 7;

    /**
     * Whether this document carries prices tied to a metal rate.
     *
     * Only jewellery and quotations have metal lines whose value
     * follows the daily rate; a tiles or general invoice prices by
     * area or piece, where "rate as on" means nothing.
     */
    public function appliesTo(Invoice $invoice): bool
    {
        return $invoice->items->contains(
            fn ($item) => filled($item->metal_type) || filled($item->purity),
        );
    }

    /**
     * The day the rate behind these prices was struck.
     *
     * Defaults to the document's own date: staff build a quotation
     * on the day they price it, so that date is the honest answer
     * when nothing more precise was recorded.
     */
    public function effectiveDate(Invoice $invoice): ?Carbon
    {
        return $invoice->rate_locked_at
            ? Carbon::parse($invoice->rate_locked_at)
            : ($invoice->invoice_date ? Carbon::parse($invoice->invoice_date) : null);
    }

    /**
     * Whole days the locked rate has been standing.
     *
     * Null when there is nothing to age — no date, or no metal
     * lines — so callers can tell "not applicable" from "fresh".
     */
    public function ageInDays(Invoice $invoice, ?Carbon $asOf = null): ?int
    {
        $date = $this->effectiveDate($invoice);

        if ($date === null || ! $this->appliesTo($invoice)) {
            return null;
        }

        return max((int) $date->diffInDays($asOf ?? now()), 0);
    }

    /**
     * Whether the locked rate is old enough that staff should
     * re-confirm it before billing.
     *
     * Deliberately only about age: it cannot know whether the
     * market actually moved, so it warns rather than blocks.
     */
    public function isStale(Invoice $invoice, ?Carbon $asOf = null, ?int $threshold = null): bool
    {
        $age = $this->ageInDays($invoice, $asOf);

        return $age !== null && $age >= ($threshold ?? self::STALE_DAYS);
    }

    /**
     * Carry a quotation's locked rate onto the invoice it becomes.
     *
     * The customer agreed to a price at the quotation's rate date.
     * Billing on a later day must not silently re-date that price —
     * otherwise a rate move turns into a surprise on the bill.
     */
    public function inheritFrom(Invoice $source, Invoice $copy): void
    {
        if ($source->rate_locked_at !== null) {
            $copy->rate_locked_at = $source->rate_locked_at;
        }
    }
}

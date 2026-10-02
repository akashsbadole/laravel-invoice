<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * Credit terms for a customer.
 *
 * Kept out of the request classes because the grand total is only known after
 * the calculation service has run, and both the create and update paths have to
 * apply the identical rule.
 */
class CreditLimitService
{
    /**
     * Whether this document would push the customer past their credit limit.
     *
     * Only payable documents count against a limit: a quotation is a proposal
     * and a delivery challan is a goods movement, so neither is money owed and
     * neither should block being quoted.
     *
     * @param  float  $grandTotal  the value of the document being written
     * @param  Invoice|null  $existing  ignored when saving an existing invoice
     */
    public function exceeds(Customer $customer, string $documentType, float $grandTotal, ?Invoice $existing = null): bool
    {
        if (! DocumentType::from($documentType)->isSale()) {
            return false;
        }

        if (! $customer->hasCreditLimit()) {
            return false;
        }

        $outstanding = $customer->creditOutstanding();

        // Re-saving an invoice must not count its own balance twice.
        if ($existing !== null) {
            $outstanding -= (float) $existing->balance_amount;
        }

        return ($outstanding + $grandTotal) > (float) $customer->credit_limit;
    }

    /**
     * The message shown when a customer is over their limit.
     */
    public function errorMessage(Customer $customer, float $grandTotal): string
    {
        $projected = $customer->creditOutstanding() + $grandTotal;
        $limit = (float) $customer->credit_limit;

        return "{$customer->full_name} is over their credit limit. "
            .'Balance after this invoice would be '.number_format($projected, 2)
            .' against a limit of '.number_format($limit, 2).'.';
    }

    /**
     * The due date to use for a document.
     *
     * The customer's credit days win over an empty due date, which is how
     * "Net 30" works without staff having to compute a date every time. An
     * explicit date the user typed is always respected.
     */
    public function dueDate(Customer $customer, string $invoiceDate, ?string $submitted): ?string
    {
        if (filled($submitted)) {
            return $submitted;
        }

        return $customer->creditDueDate(Carbon::parse($invoiceDate))?->toDateString();
    }
}

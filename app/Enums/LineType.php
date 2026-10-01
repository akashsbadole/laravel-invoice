<?php

namespace App\Enums;

/**
 * What an invoice line does to the amount owed.
 */
enum LineType: string
{
    case Sale = 'sale';
    case ExchangeCredit = 'exchange_credit';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::ExchangeCredit => 'Old gold / exchange credit',
        };
    }

    /**
     * Exchange credit is the customer's own metal handed back to the shop, so
     * it is not a taxable supply and never carries charges.
     */
    public function isTaxable(): bool
    {
        return $this === self::Sale;
    }

    public function supportsCharges(): bool
    {
        return $this === self::Sale;
    }
}

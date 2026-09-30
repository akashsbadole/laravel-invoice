<?php

namespace App\Enums;

/**
 * How a charge type's rate is turned into a currency amount.
 */
enum ChargeCalculationType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case PerGram = 'per_gram';
    case PerCarat = 'per_carat';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::Percentage => '% of item value',
            self::PerGram => 'Per gram (net weight)',
            self::PerCarat => 'Per carat (stone weight)',
        };
    }

    /**
     * Invoice-level charges have no per-item weight to multiply against,
     * so only these calculation types are valid for them.
     */
    public static function invoiceLevelTypes(): array
    {
        return [self::Fixed, self::Percentage];
    }
}

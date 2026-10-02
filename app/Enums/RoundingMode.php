<?php

namespace App\Enums;

/**
 * How the invoice total is rounded before it is shown to the customer.
 */
enum RoundingMode: string
{
    case NearestRupee = 'nearest_rupee';
    case TwoDecimals = 'two_decimals';

    public function label(): string
    {
        return match ($this) {
            self::NearestRupee => 'Nearest rupee',
            self::TwoDecimals => 'Two decimals',
        };
    }

    /**
     * Apply this mode to a computed total.
     */
    public function apply(float $value): float
    {
        return match ($this) {
            self::NearestRupee => (float) round($value, 0),
            self::TwoDecimals => round($value, 2),
        };
    }
}

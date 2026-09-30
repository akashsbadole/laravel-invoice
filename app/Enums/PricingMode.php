<?php

namespace App\Enums;

enum PricingMode: string
{
    case Manual = 'manual';
    case JewelryCalculated = 'jewelry_calculated';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual Amount',
            self::JewelryCalculated => 'Jewelry Calculation',
        };
    }
}

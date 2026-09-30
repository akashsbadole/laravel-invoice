<?php

namespace App\Enums;

/**
 * How an invoice item's `rate` field is turned into its base value,
 * before any charges (making, wastage, stone, ...) are added.
 */
enum RateType: string
{
    case PerGram = 'per_gram';
    case PerCarat = 'per_carat';
    case PerPiece = 'per_piece';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PerGram => 'Per gram (× net weight)',
            self::PerCarat => 'Per carat (× stone carat)',
            self::PerPiece => 'Per piece (× quantity)',
            self::Fixed => 'Fixed amount (manual)',
        };
    }
}

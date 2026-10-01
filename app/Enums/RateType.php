<?php

namespace App\Enums;

/**
 * How an invoice item's `rate` field is turned into its base value,
 * before any charges (making, wastage, freight, ...) are added.
 *
 * Which of these are offered depends on the tenant's industry — see
 * config/industries.php.
 */
enum RateType: string
{
    case PerGram = 'per_gram';
    case PerCarat = 'per_carat';
    case PerPiece = 'per_piece';
    case Fixed = 'fixed';
    case PerSqft = 'per_sqft';
    case PerSqm = 'per_sqm';
    case PerMeter = 'per_meter';
    case PerKg = 'per_kg';
    case PerBox = 'per_box';
    case PerUnit = 'per_unit';
    case PerLitre = 'per_litre';

    public function label(): string
    {
        return match ($this) {
            self::PerGram => 'Per gram (× net weight)',
            self::PerCarat => 'Per carat (× stone carat)',
            self::PerPiece => 'Per piece (× quantity)',
            self::Fixed => 'Fixed amount (manual)',
            self::PerSqft => 'Per sq ft (× area)',
            self::PerSqm => 'Per sq m (× area)',
            self::PerMeter => 'Per metre (× running length)',
            self::PerKg => 'Per kg (× weight)',
            self::PerBox => 'Per box (× boxes)',
            self::PerUnit => 'Per unit (× quantity)',
            self::PerLitre => 'Per litre (× volume)',
        };
    }

    /**
     * Rate types whose base value multiplies by a quantity-like measure
     * rather than using the rate as-is.
     */
    public function isDerived(): bool
    {
        return $this !== self::Fixed;
    }

    /**
     * Rate types billed against a surface area (length × width).
     */
    public function isAreaBased(): bool
    {
        return $this === self::PerSqft || $this === self::PerSqm;
    }
}

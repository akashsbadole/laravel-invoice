<?php

namespace App\Enums;

/**
 * How a customer's GSTIN should be treated on an e-invoice.
 *
 * `Unregistered` and `Consumer` are B2C cases: the buyer has no GSTIN, so the
 * document must not claim one.
 */
enum GstinType: string
{
    case Regular = 'regular';
    case Composition = 'composition';
    case Unregistered = 'unregistered';
    case Consumer = 'consumer';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Composition => 'Composition',
            self::Unregistered => 'Unregistered',
            self::Consumer => 'Consumer (B2C)',
        };
    }

    /**
     * Whether this customer transacts as a business with a GSTIN.
     *
     * A registered GSTIN is what makes a B2B e-invoice possible; the two
     * B2C categories explicitly have none.
     */
    public function isBusiness(): bool
    {
        return $this === self::Regular || $this === self::Composition;
    }
}

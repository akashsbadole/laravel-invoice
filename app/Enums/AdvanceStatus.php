<?php

namespace App\Enums;

/**
 * A booking / advance payment taken from a customer before the invoice
 * exists. The money is real (it shows in collected totals), it just has
 * nowhere to sit until it is applied to a document.
 */
enum AdvanceStatus: string
{
    case Available = 'available';
    case Applied = 'applied';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Applied => 'Applied',
            self::Refunded => 'Refunded',
        };
    }
}

<?php

namespace App\Enums;

/**
 * A customer's pricing category.
 *
 * The tier is a label the sales team assigns; resolving it to an actual rate
 * is done per invoice line rather than automatically, so this is deliberately
 * not wired into the calculator yet.
 */
enum PriceTier: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A — list price',
            self::B => 'B — trade',
            self::C => 'C — wholesale',
        };
    }
}

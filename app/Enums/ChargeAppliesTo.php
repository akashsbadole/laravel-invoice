<?php

namespace App\Enums;

enum ChargeAppliesTo: string
{
    case Item = 'item';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Item => 'Per invoice item',
            self::Invoice => 'Whole invoice',
        };
    }
}

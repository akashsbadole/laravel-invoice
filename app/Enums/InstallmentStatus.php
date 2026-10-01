<?php

namespace App\Enums;

/**
 * One slice of an agreed payment plan.
 */
enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Waived => 'Waived',
        };
    }

    public function isSettled(): bool
    {
        return $this !== self::Pending;
    }
}

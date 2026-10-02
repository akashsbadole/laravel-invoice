<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Converted = 'converted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Converted => 'Converted',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::PartiallyPaid => 'amber',
            self::Paid => 'green',
            self::Overdue => 'red',
            self::Cancelled => 'slate',
            self::Refunded => 'blue',
            self::Draft => 'slate',
            self::Sent => 'amber',
            self::Accepted => 'green',
            self::Converted => 'blue',
            self::Closed => 'slate',
        };
    }

    /**
     * Statuses that still count as open (unsettled) invoices.
     *
     * @return array<int, self>
     */
    public static function openStatuses(): array
    {
        return [self::Unpaid, self::PartiallyPaid, self::Overdue];
    }
}

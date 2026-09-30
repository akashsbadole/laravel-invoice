<?php

namespace App\Enums;

enum InvoiceEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Sent = 'sent';
    case LinkViewed = 'link_viewed';
    case LinkDownloaded = 'link_downloaded';
    case PaymentRecorded = 'payment_recorded';
    case ReminderSent = 'reminder_sent';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Invoice created',
            self::Updated => 'Invoice updated',
            self::Sent => 'Invoice sent',
            self::LinkViewed => 'Share link viewed',
            self::LinkDownloaded => 'PDF downloaded',
            self::PaymentRecorded => 'Payment recorded',
            self::ReminderSent => 'Reminder sent',
            self::Cancelled => 'Invoice cancelled',
            self::Refunded => 'Invoice refunded',
        };
    }
}

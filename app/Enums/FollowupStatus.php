<?php

namespace App\Enums;

enum FollowupStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case WaitingForResponse = 'waiting_for_response';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Contacted => 'Contacted',
            self::WaitingForResponse => 'Waiting for Response',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}

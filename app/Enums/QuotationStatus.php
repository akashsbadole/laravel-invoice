<?php

namespace App\Enums;

/**
 * Where a quotation sits in the agree/reject cycle. Separate from
 * InvoiceStatus because a quotation is never "paid".
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Converted => 'Converted',
        };
    }

    /**
     * A customer decision closes the quotation; staff can still reopen a
     * draft but not overrule a decision.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }

    public function isDecided(): bool
    {
        return ! $this->isOpen();
    }
}

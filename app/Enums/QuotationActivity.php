<?php

namespace App\Enums;

/**
 * Something a customer did with a shared quotation.
 *
 * Deliberately separate from QuotationStatus: a status is where the document
 * sits, while an activity is the event that changed it. "Viewed" is the clearest
 * case — it moves no status at all, yet it is precisely what a shop needs to
 * know before deciding whether to chase.
 */
enum QuotationActivity: string
{
    case Viewed = 'viewed';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Viewed => 'Quotation opened',
            self::Accepted => 'Quotation accepted',
            self::Declined => 'Quotation declined',
        };
    }

    /**
     * One-line summary shared by the in-app notification, the email subject and
     * the stored payload, so the three can never drift apart.
     */
    public function headline(string $customer, string $number): string
    {
        return match ($this) {
            self::Viewed => "{$customer} opened quotation {$number}.",
            self::Accepted => "{$customer} accepted quotation {$number}.",
            self::Declined => "{$customer} declined quotation {$number}.",
        };
    }
}

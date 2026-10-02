<?php

namespace App\Enums;

/**
 * How the business would rather reach this customer.
 *
 * Reminder automation consults this before sending, so a customer who prefers
 * a phone call is not emailed a payment reminder.
 */
enum ContactChannel: string
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
    case Call = 'call';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::Call => 'Phone call',
        };
    }

    /**
     * Whether this channel can carry an automated message.
     *
     * `Call` is a human channel, so reminder jobs skip it rather than
     * pretending a text was sent.
     */
    public function isAutomatable(): bool
    {
        return $this !== self::Call;
    }
}

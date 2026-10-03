<?php

namespace App\Services;

use App\Enums\ContactChannel;
use App\Enums\DocumentType;
use App\Mail\QuotationFollowUpMail;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\MessageLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Chases a quotation the customer has gone quiet on.
 *
 * Deliberately the mirror of PaymentReminderService: same channel rules, same
 * message_logs trail, same throttle column. `last_reminder_sent_at` is safe to
 * share because a quotation carries no balance — the payment service stands
 * down before it ever stamps a quote (see its `balance_amount <= 0` guard).
 */
class QuotationFollowUpService
{
    /** Days before the same quotation may be nudged again. */
    public const THROTTLE_DAYS = 3;

    /** How close to expiry a quotation counts as "about to lapse". */
    public const EXPIRING_WINDOW_DAYS = 3;

    public function __construct(
        private readonly QuotationService $quotations,
        private readonly SmsService $sms,
    ) {}

    /**
     * Send a quotation to the customer when it is first marked as sent.
     *
     * Creates a share link if none exists, then delivers via the customer's
     * preferred contact channel. Returns the channels that actually went out.
     *
     * @return array<string, string>
     */
    public function sendQuotation(Invoice $quotation, ?int $byUserId = null): array
    {
        $quotation->loadMissing('customer', 'shareLinks');
        $settings = BusinessSetting::forTenant($quotation->tenant_id);

        if (! $settings->quotation_followup_enabled) {
            return [];
        }

        if (! $this->isChaseable($quotation)) {
            return [];
        }

        $link = $this->activeLink($quotation);

        if ($link === null) {
            $link = $this->createShareLink($quotation);
        }

        $url = route('invoices.public.show', $link->token);
        $sent = [];
        $preference = $quotation->customer?->preferred_contact_channel;

        $wantsText = $preference === null
            || in_array($preference, [ContactChannel::Sms, ContactChannel::WhatsApp], true);
        $wantsEmail = $preference === null || $preference === ContactChannel::Email;
        $automate = $preference?->isAutomatable() ?? true;

        if ($automate && $wantsText && $quotation->customer?->mobile_number) {
            $sent['sms'] = $this->sms->send(
                $quotation->customer->mobile_number,
                $this->sendMessage($quotation, $settings, $url),
                [
                    'customer_id' => $quotation->customer_id,
                    'invoice_id' => $quotation->id,
                    'created_by' => $byUserId,
                ],
            )->status;
        }

        if ($automate && $wantsEmail && $quotation->customer?->email) {
            $sent['email'] = $this->sendEmail($quotation, $url, $byUserId);
        }

        if ($sent !== [] || ! $automate) {
            $quotation->forceFill(['last_reminder_sent_at' => now()])->saveQuietly();
        }

        return $sent;
    }

    /**
     * Create an active share link for the quotation.
     */
    protected function createShareLink(Invoice $quotation): InvoiceShareLink
    {
        $link = new InvoiceShareLink([
            'invoice_id' => $quotation->id,
            'is_active' => true,
            'created_by' => null,
            'expires_at' => $quotation->quotation_valid_until,
        ]);

        $link->token = InvoiceShareLink::generateToken();
        $link->saveQuietly();

        return $link;
    }

    /**
     * Initial send message for a newly sent quotation.
     */
    protected function sendMessage(Invoice $quotation, BusinessSetting $settings, string $url): string
    {
        $valid = $quotation->quotation_valid_until
            ? ' Valid until '.$quotation->quotation_valid_until->format('d M').'.'
            : '';

        return sprintf(
            'Dear %s, please find your quotation %s from %s.%s View it here: %s',
            $quotation->customer->full_name,
            $quotation->invoice_number,
            $settings->business_name,
            $valid,
            $url,
        );
    }

    /**
     * Nudge one quotation. Returns the channels that actually went out.
     *
     * @return array<string, string>
     */
    public function sendForQuotation(Invoice $quotation, ?int $byUserId = null, bool $force = false): array
    {
        $quotation->loadMissing('customer', 'shareLinks');
        $settings = BusinessSetting::forTenant($quotation->tenant_id);

        if (! $force && ! $settings->quotation_followup_enabled) {
            return [];
        }

        // A nudge needs an open quotation the customer can still act on, and a
        // link to send them to.
        if (! $this->isChaseable($quotation) || $this->activeLink($quotation) === null) {
            return [];
        }

        // The scheduled sweep only sends what the rules say is due. A staff member
        // pressing the button overrides the schedule, not the state.
        if (! $force && ! $this->isDue($quotation)) {
            return [];
        }

        $link = $this->activeLink($quotation);
        $url = route('invoices.public.show', $link->token);
        $sent = [];
        $preference = $quotation->customer?->preferred_contact_channel;

        // A customer who asked for one channel should not get both. WhatsApp and
        // SMS are both delivered by the SMS driver here, so WhatsApp maps onto
        // the text send; `call` is a human channel, so automation stands down.
        $wantsText = $preference === null
            || in_array($preference, [ContactChannel::Sms, ContactChannel::WhatsApp], true);
        $wantsEmail = $preference === null || $preference === ContactChannel::Email;
        $automate = $preference?->isAutomatable() ?? true;

        if ($automate && $wantsText && $quotation->customer?->mobile_number) {
            $sent['sms'] = $this->sms->send(
                $quotation->customer->mobile_number,
                $this->textMessage($quotation, $settings, $url),
                [
                    'customer_id' => $quotation->customer_id,
                    'invoice_id' => $quotation->id,
                    'created_by' => $byUserId,
                ],
            )->status;
        }

        if ($automate && $wantsEmail && $quotation->customer?->email) {
            $sent['email'] = $this->email($quotation, $url, $byUserId);
        }

        // Stamp either way: when a message went out so the throttle applies, and
        // when the customer asked for a call so the nightly job does not
        // reconsider the same quotation every evening.
        if ($sent !== [] || ! $automate) {
            $quotation->forceFill(['last_reminder_sent_at' => now()])->saveQuietly();
        }

        return $sent;
    }

    /**
     * Quotations a nudge is due for, across the current tenant.
     *
     * @return Collection<int, Invoice>
     */
    public function pending(): Collection
    {
        return Invoice::query()
            ->where('document_type', DocumentType::Quotation->value)
            ->with(['customer', 'shareLinks'])
            ->get()
            ->filter(fn (Invoice $quotation): bool => $this->isDue($quotation))
            ->values();
    }

    /**
     * Whether a nudge is due for this quotation.
     *
     * Two situations leave the shop waiting on someone else:
     *  - sent but never opened (it may never have arrived), and
     *  - still open but about to lapse.
     */
    public function isDue(Invoice $quotation): bool
    {
        if (! $this->isChaseable($quotation) || $this->recentlyNudged($quotation)) {
            return false;
        }

        if ($this->activeLink($quotation) === null) {
            return false;
        }

        return $this->neverOpened($quotation) || $this->expiringSoon($quotation);
    }

    protected function neverOpened(Invoice $quotation): bool
    {
        $link = $this->activeLink($quotation);

        if ($link === null || $link->viewed_at !== null) {
            return false;
        }

        $sentOn = $quotation->invoice_date ?? $quotation->created_at;
        $days = max((int) (BusinessSetting::forTenant($quotation->tenant_id)->quotation_followup_days ?? 2), 0);

        return $sentOn !== null && $sentOn->lte(now()->subDays($days));
    }

    protected function expiringSoon(Invoice $quotation): bool
    {
        return $quotation->quotation_valid_until !== null
            && $quotation->quotation_valid_until->lte(now()->addDays(self::EXPIRING_WINDOW_DAYS));
    }

    /**
     * Open, not converted, and actually a quotation — the only states a customer
     * can still act on.
     */
    protected function isChaseable(Invoice $quotation): bool
    {
        return $quotation->document_type->isQuotation()
            && $quotation->converted_to_id === null
            && $this->quotations->currentStatus($quotation)->isOpen();
    }

    protected function activeLink(Invoice $quotation): ?InvoiceShareLink
    {
        return $quotation->shareLinks
            ->where('is_active', true)
            ->sortByDesc('id')
            ->first();
    }

    protected function recentlyNudged(Invoice $quotation): bool
    {
        return $quotation->last_reminder_sent_at !== null
            && $quotation->last_reminder_sent_at->gt(now()->subDays(self::THROTTLE_DAYS));
    }

    protected function textMessage(Invoice $quotation, BusinessSetting $settings, string $url): string
    {
        $valid = $quotation->quotation_valid_until
            ? ' Valid until '.$quotation->quotation_valid_until->format('d M').'.'
            : '';

        return sprintf(
            'Dear %s, your quotation %s from %s is still open.%s View it here: %s',
            $quotation->customer->full_name,
            $quotation->invoice_number,
            $settings->business_name,
            $valid,
            $url,
        );
    }

    protected function email(Invoice $quotation, string $url, ?int $byUserId): string
    {
        $log = new MessageLog([
            'channel' => 'email',
            'driver' => 'mail',
            'to' => (string) $quotation->customer->email,
            'body' => "Quotation follow-up for {$quotation->invoice_number}",
            'status' => 'failed',
            'customer_id' => $quotation->customer_id,
            'invoice_id' => $quotation->id,
            'created_by' => $byUserId,
        ]);

        try {
            Mail::to($quotation->customer->email)->send(
                new QuotationFollowUpMail(
                    $quotation->loadMissing('customer'),
                    $url,
                    $quotation->quotation_valid_until,
                ),
            );

            $log->status = 'sent';
        } catch (Throwable $e) {
            $log->error = mb_substr($e->getMessage(), 0, 500);
            Log::warning("Quotation follow-up email to {$log->to} failed: {$e->getMessage()}");
        }

        $log->save();

        return $log->status;
    }
}

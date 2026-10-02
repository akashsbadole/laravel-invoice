<?php

namespace App\Services;

use App\Enums\ContactChannel;
use App\Mail\PaymentReminderMail;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\MessageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Chases money owed on an invoice across SMS, email and the share link.
 *
 * Every attempt is written to message_logs so the customer timeline shows
 * what was actually sent, and `last_reminder_sent_at` throttles the nightly
 * job so a customer is not chased every evening.
 */
class PaymentReminderService
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * Remind about one invoice. Staff-triggered, so it ignores the nightly
     * throttle but still honours the tenant's channel preferences.
     */
    public function sendForInvoice(Invoice $invoice, ?int $byUserId = null, bool $force = false): array
    {
        $invoice->loadMissing('customer', 'shareLinks');
        $settings = BusinessSetting::forTenant($invoice->tenant_id);

        if ((float) $invoice->balance_amount <= 0) {
            return [];
        }

        if (! $force && $this->recentlyReminded($invoice)) {
            return [];
        }

        $sent = [];
        $due = $invoice->due_date;
        $amount = number_format((float) $invoice->balance_amount, 2);
        $link = $this->shareUrl($invoice);
        $preference = $invoice->customer?->preferred_contact_channel;

        // A customer who asked for one channel should not get both. WhatsApp
        // and SMS are both delivered by the SMS driver here, so WhatsApp maps
        // onto the text send.
        $wantsText = $preference === null
            || in_array($preference, [ContactChannel::Sms, ContactChannel::WhatsApp], true);
        $wantsEmail = $preference === null || $preference === ContactChannel::Email;
        // `call` is a human channel, so automation stands down entirely and the
        // reminder surfaces on the staff dashboard instead.
        $automate = $preference?->isAutomatable() ?? true;

        // SMS goes to the mobile number the customer gave us.
        if ($automate && $wantsText && $settings->sms_payment_reminders && $invoice->customer?->mobile_number) {
            $message = sprintf(
                'Dear %s, a reminder that invoice %s has Rs.%s outstanding%s. %s',
                $invoice->customer->full_name,
                $invoice->invoice_number,
                $amount,
                $due ? ' (due '.$due->format('d M').')' : '',
                $link !== null ? "View & pay: {$link}" : '',
            );

            $sent['sms'] = $this->sms->send($invoice->customer->mobile_number, $message, [
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'created_by' => $byUserId,
            ])->status;
        }

        // Email goes to the address on file, gated separately from SMS so a
        // tenant can run either channel on its own.
        if ($automate && $wantsEmail && $settings->email_payment_reminders && $invoice->customer?->email) {
            $sent['email'] = $this->email($invoice, $amount, $due, $link, $byUserId);
        }

        // Stamp the invoice either way: when a message went out so the throttle
        // applies, and when the customer asked for a call so the nightly job
        // does not reconsider the same invoice every night.
        if ($sent !== [] || ! $automate) {
            $invoice->forceFill(['last_reminder_sent_at' => now()])->saveQuietly();
        }

        return $sent;
    }

    protected function email(Invoice $invoice, string $amount, ?Carbon $due, ?string $link, ?int $byUserId): string
    {
        $log = new MessageLog([
            'channel' => 'email',
            'driver' => 'mail',
            'to' => (string) $invoice->customer->email,
            'body' => "Payment reminder for invoice {$invoice->invoice_number}",
            'status' => 'failed',
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'created_by' => $byUserId,
        ]);

        try {
            Mail::to($invoice->customer->email)->send(
                new PaymentReminderMail(
                    $invoice->loadMissing('customer'),
                    $amount,
                    $due,
                    $link,
                ),
            );

            $log->status = 'sent';
        } catch (Throwable $e) {
            $log->error = mb_substr($e->getMessage(), 0, 500);
            Log::warning("Reminder email to {$log->to} failed: {$e->getMessage()}");
        }

        $log->save();

        return $log->status;
    }

    protected function shareUrl(Invoice $invoice): ?string
    {
        $token = $invoice->shareLinks()->where('is_active', true)->latest()->value('token');

        return $token !== null ? route('invoices.public.show', $token) : null;
    }

    protected function recentlyReminded(Invoice $invoice): bool
    {
        return $invoice->last_reminder_sent_at !== null
            && $invoice->last_reminder_sent_at->gt(now()->subDays(3));
    }
}

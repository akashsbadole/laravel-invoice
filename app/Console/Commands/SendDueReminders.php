<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\DueRemindersDigest;
use App\Services\ReminderService;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send {--no-sms : Skip automatic SMS to customers}';

    protected $description = 'Send the daily reminder digest to staff and (optionally) SMS reminders to customers';

    public function handle(ReminderService $reminders, SmsService $sms): int
    {
        $data = $reminders->gather();
        $summary = [
            'payments' => $data['payments']->count(),
            'followups' => $data['followups']->count(),
            'birthdays' => $data['birthdays']->count(),
            'anniversaries' => $data['anniversaries']->count(),
            'custom' => $data['custom']->count(),
        ];

        if (array_sum($summary) > 0) {
            $staff = User::query()->where('is_active', true)->whereIn('role', ['admin', 'invoice_creator'])->get();

            foreach ($staff as $user) {
                try {
                    $user->notify(new DueRemindersDigest($summary));
                } catch (Throwable $e) {
                    report($e);
                    $this->warn("Could not notify {$user->email}: {$e->getMessage()}");
                }
            }

            $this->info('Digest sent to '.$staff->count().' staff member(s).');
        }

        if (! $this->option('no-sms')) {
            $business = BusinessSetting::current();

            if ($business->sms_payment_reminders) {
                $this->smsOverdue($data['payments'], $sms, $business->business_name);
            }

            if ($business->sms_birthday_wishes) {
                $this->smsBirthdays($reminders->upcoming('birthday', today(), 0), $sms, $business->business_name);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int,Invoice>  $invoices
     */
    protected function smsOverdue(Collection $invoices, SmsService $sms, string $businessName): void
    {
        $sent = 0;

        foreach ($invoices as $invoice) {
            $overdue = $invoice->due_date && $invoice->due_date->isPast();
            $recentlyReminded = $invoice->last_reminder_sent_at && $invoice->last_reminder_sent_at->gt(now()->subDays(3));

            if (! $overdue || $recentlyReminded || ! $invoice->customer?->mobile_number) {
                continue;
            }

            $log = $sms->send($invoice->customer->mobile_number, sprintf(
                'Dear %s, invoice %s has Rs.%s pending (was due %s). Please pay at your earliest. - %s',
                $invoice->customer->full_name,
                $invoice->invoice_number,
                number_format((float) $invoice->balance_amount, 2),
                $invoice->due_date->format('d M'),
                $businessName,
            ), ['customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id]);

            if ($log->status !== 'failed') {
                $invoice->forceFill(['last_reminder_sent_at' => now()])->saveQuietly();
                $sent++;
            }
        }

        $this->info("{$sent} payment reminder SMS processed.");
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $customers
     */
    protected function smsBirthdays(Collection $customers, SmsService $sms, string $businessName): void
    {
        foreach ($customers as $customer) {
            $sms->send((string) $customer['mobile_number'], "Happy Birthday {$customer['full_name']}! Wishing you a wonderful year ahead. - {$businessName}", [
                'customer_id' => $customer['id'],
            ]);
        }

        $this->info($customers->count().' birthday SMS processed.');
    }
}

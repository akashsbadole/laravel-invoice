<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DueRemindersDigest;
use App\Services\PaymentReminderService;
use App\Services\ReminderService;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send {--no-sms : Skip automatic SMS to customers}';

    protected $description = 'Send the daily reminder digest to staff and (optionally) SMS reminders to customers';

    public function __construct(private readonly PaymentReminderService $paymentReminders)
    {
        parent::__construct();
    }

    public function handle(ReminderService $reminders, SmsService $sms): int
    {
        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            Tenant::runInContext($tenant->id, function () use ($reminders, $sms) {
                $this->handleTenant($reminders, $sms);
            });
        }

        return self::SUCCESS;
    }

    protected function handleTenant(ReminderService $reminders, SmsService $sms): void
    {
        $data = $reminders->gather();
        $summary = [
            'payments' => $data['payments']->count(),
            'followups' => $data['followups']->count(),
            'birthdays' => $data['birthdays']->count(),
            'anniversaries' => $data['anniversaries']->count(),
            'custom' => $data['custom']->count(),
            'expiring_quotes' => $data['expiring_quotes']->count(),
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

            // One service drives both channels and the 3-day throttle, so
            // staff nudges and the nightly job cannot double-send. Each
            // channel is gated by its own tenant setting.
            if ($business->sms_payment_reminders || $business->email_payment_reminders) {
                $this->remindOverdue($data['payments']);
            }

            if ($business->sms_birthday_wishes) {
                $this->smsBirthdays($reminders->upcoming('birthday', today(), 0), $sms, $business->business_name);
            }

            if ($business->sms_anniversary_wishes) {
                $this->smsOccasions(
                    $reminders->upcoming('anniversary', today(), 0),
                    $sms,
                    $business->business_name,
                    'Happy Anniversary'
                );
            }
        }
    }

    /**
     * Only chase invoices that are actually past their due date; the service
     * handles the per-channel delivery and the 3-day throttle.
     *
     * @param  Collection<int,Invoice>  $invoices
     */
    protected function remindOverdue(Collection $invoices): void
    {
        $sent = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->due_date || ! $invoice->due_date->isPast()) {
                continue;
            }

            if ($this->paymentReminders->sendForInvoice($invoice) !== []) {
                $sent++;
            }
        }

        $this->info("{$sent} payment reminder(s) processed.");
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $customers
     */
    protected function smsBirthdays(Collection $customers, SmsService $sms, string $businessName): void
    {
        $this->smsOccasions($customers, $sms, $businessName, 'Happy Birthday');
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $customers
     */
    protected function smsOccasions(Collection $customers, SmsService $sms, string $businessName, string $greeting): void
    {
        foreach ($customers as $customer) {
            $sms->send(
                (string) $customer['mobile_number'],
                "{$greeting} {$customer['full_name']}! Wishing you a wonderful day ahead. - {$businessName}",
                ['customer_id' => $customer['id']],
            );
        }

        $this->info($customers->count()." {$greeting} SMS processed.");
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DueRemindersDigest extends Notification
{
    /**
     * @param  array<string,int>  $summary  payments, followups, birthdays, anniversaries, custom, expiring_quotes
     */
    public function __construct(public array $summary) {}

    /**
     * @return array<int,string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = [
            'payments' => 'payment(s) due or overdue',
            'followups' => 'customer follow-up(s)',
            'birthdays' => 'upcoming birthday(s)',
            'anniversaries' => 'upcoming anniversary(ies)',
            'custom' => 'custom reminder(s)',
            'expiring_quotes' => 'quotation(s) expiring within 3 days',
        ];

        $mail = (new MailMessage)
            ->subject('Your reminders for today')
            ->greeting("Hello {$notifiable->name},");

        foreach ($labels as $key => $label) {
            if (($this->summary[$key] ?? 0) > 0) {
                $mail->line("{$this->summary[$key]} {$label}");
            }
        }

        return $mail->action('Open reminders', url('/reminders'));
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'daily_digest', 'summary' => $this->summary];
    }
}

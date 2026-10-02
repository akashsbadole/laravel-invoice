<?php

namespace App\Notifications;

use App\Enums\QuotationActivity;
use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the shop what a customer just did with a shared quotation.
 *
 * Until this existed, an accepted quotation changed two columns and an event —
 * nothing else. The only way to find out the customer had answered was to
 * re-open the list and look, which is exactly the work this removes.
 */
class QuotationActivityNotification extends Notification
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public QuotationActivity $activity,
        public int $invoiceId,
        public string $invoiceNumber,
        public string $customerName,
        public string $headline,
        public ?string $detail = null,
        public array $channels = ['database'],
    ) {}

    /**
     * @param  list<string>  $channels
     */
    public static function fromInvoice(
        QuotationActivity $activity,
        Invoice $quotation,
        ?string $detail = null,
        array $channels = ['database'],
    ): self {
        $customer = $quotation->customer?->full_name ?? 'A customer';
        $number = (string) $quotation->invoice_number;

        return new self(
            activity: $activity,
            invoiceId: $quotation->id,
            invoiceNumber: $number,
            customerName: $customer,
            headline: $activity->headline($customer, $number),
            detail: $detail,
            channels: $channels,
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->activity->label().' — '.$this->invoiceNumber)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->headline);

        if ($this->detail !== null && $this->detail !== '') {
            $mail->line($this->detail);
        }

        return $mail->action('Open the quotation', route('invoices.show', $this->invoiceId));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quotation_activity',
            'activity' => $this->activity->value,
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'customer' => $this->customerName,
            'detail' => $this->detail,
            'message' => $this->headline,
        ];
    }
}

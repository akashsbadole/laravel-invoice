<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * A short payment nudge. Deliberately not the full PDF — the customer already
 * has the invoice; this is about the balance.
 */
class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $amount,
        public ?Carbon $dueDate = null,
        public ?string $shareUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $business = BusinessSetting::forTenant($this->invoice->tenant_id);

        return new Envelope(
            subject: "Payment reminder: {$this->invoice->invoice_number} ({$business->business_name})",
        );
    }

    public function content(): Content
    {
        $this->invoice->loadMissing('customer');

        return new Content(
            markdown: 'mail.payment-reminder',
            with: [
                'invoice' => $this->invoice,
                'business' => BusinessSetting::forTenant($this->invoice->tenant_id),
                'amount' => $this->amount,
                'dueDate' => $this->dueDate,
                'shareUrl' => $this->shareUrl,
            ],
        );
    }
}

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
 * A short nudge about a quotation nobody has answered yet.
 *
 * Deliberately not the full PDF — the customer already has the share link; this
 * is about moving the decision along.
 */
class QuotationFollowUpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $quotation,
        public ?string $shareUrl = null,
        public ?Carbon $validUntil = null,
    ) {}

    public function envelope(): Envelope
    {
        $business = BusinessSetting::forTenant($this->quotation->tenant_id);

        return new Envelope(
            subject: "Your quotation {$this->quotation->invoice_number} ({$business->business_name})",
        );
    }

    public function content(): Content
    {
        $this->quotation->loadMissing('customer');

        return new Content(
            markdown: 'mail.quotation-follow-up',
            with: [
                'quotation' => $this->quotation,
                'business' => BusinessSetting::forTenant($this->quotation->tenant_id),
                'shareUrl' => $this->shareUrl,
                'validUntil' => $this->validUntil,
            ],
        );
    }
}

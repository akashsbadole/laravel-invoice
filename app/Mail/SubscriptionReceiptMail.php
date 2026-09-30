<?php

namespace App\Mail;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public Plan $plan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment receipt — {$this->plan->name} plan",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mail.subscription-receipt', [
                'plan' => $this->plan,
                'subscription' => $this->subscription,
                'businessName' => $this->subscription->tenant->businessSetting?->business_name
                    ?? $this->subscription->tenant->name,
            ])->render(),
        );
    }
}

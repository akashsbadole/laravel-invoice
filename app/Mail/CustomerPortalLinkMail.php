<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerPortalLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your secure invoice portal link');
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mail.customer-portal-link', [
                'customer' => $this->customer,
                'url' => route('portal.verify', $this->token),
            ])->render(),
        );
    }
}

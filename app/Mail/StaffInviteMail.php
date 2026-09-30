<?php

namespace App\Mail;

use App\Models\StaffInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public StaffInvite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invite->tenant->businessSetting?->business_name}.",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mail.staff-invite', [
                'invite' => $this->invite,
                'businessName' => $this->invite->tenant->businessSetting?->business_name ?? $this->invite->tenant->name,
                'acceptUrl' => route('invites.accept', $this->invite->token),
                'roleLabel' => $this->invite->role->label(),
            ])->render(),
        );
    }
}

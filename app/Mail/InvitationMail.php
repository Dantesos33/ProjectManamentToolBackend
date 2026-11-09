<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(public Invitation $invitation, public string $acceptUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invitation->tenant->name} on DeesOps",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation');
    }
}
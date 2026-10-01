<?php

namespace App\Mail;

use App\Models\InvitationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InvitationRequest $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation request — '.$this->invitation->name,
        );
    }

    public function content(): Content
    {
        return new Content('markdown', 'emails.invitation-request');
    }
}

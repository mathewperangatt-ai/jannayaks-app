<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * New-sign-in notification for the Jannayaks Security Administrator account.
 */
class SecurityAdminLoginMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $accountEmail,
        public string $whenIst,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[JANNAYAKS SECURITY] New sign-in to the Security Administrator account',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.security-admin-login',
        );
    }
}

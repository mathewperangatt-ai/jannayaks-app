<?php

namespace App\Mail;

use App\Models\Recommendation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecommendationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Recommendation $recommendation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ),
            subject: 'Someone has recommended you to Jannayaks',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recommendation-invitation',
        );
    }
}

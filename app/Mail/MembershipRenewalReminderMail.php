<?php

namespace App\Mail;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Provisional renewal / expiry reminder content (Phase 15).
 * Final wording is deliberately not finalized — replace the view later without changing lifecycle logic.
 */
class MembershipRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Membership $membership,
        public string $eventType,
        public int $offsetDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[PROVISIONAL] Jannayaks membership renewal reminder',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.membership.renewal-reminder',
        );
    }
}

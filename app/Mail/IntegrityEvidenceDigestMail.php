<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Independent integrity-evidence anchor (Wave 3): the daily signed-evidence
 * digest. Contains profile IDs, snapshot hashes, signature prefixes and
 * incident counts ONLY — no profile content, no personal data, no secrets.
 */
class IntegrityEvidenceDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $digest
     */
    public function __construct(
        public array $digest,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[JANNAYAKS] Integrity evidence digest — '.$this->digest['generated_at_ist'],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.integrity-evidence-digest',
        );
    }
}

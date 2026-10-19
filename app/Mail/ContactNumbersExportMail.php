<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Weekly contact-numbers CSV for the owner. The CSV is attached from private
 * storage; the email body contains only batch metadata — never phone numbers.
 */
class ContactNumbersExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $batchId,
        public int $rowCount,
        public string $filePath,
    ) {}

    public function envelope(): Envelope
    {
        // Subject intentionally carries no personal data.
        return new Envelope(
            subject: 'Jannayaks — weekly contact numbers export (batch '.substr($this->batchId, 0, 8).')',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.contact-numbers-export',
            with: [
                'batchId' => $this->batchId,
                'rowCount' => $this->rowCount,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('private_uploads', $this->filePath),
        ];
    }
}

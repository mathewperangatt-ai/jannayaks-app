<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, array{bytes: string, filename: string}>  $attachments
     */
    public function __construct(
        public readonly \App\Models\Payment $payment,
        public readonly array $documentAttachments,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Jannayaks payment documents ('.$this->payment->invoice_number.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-documents',
        );
    }

    public function attachments(): array
    {
        $attached = [];
        foreach ($this->documentAttachments as $attachment) {
            $attached[] = \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $attachment['bytes'])
                ->as($attachment['filename']);
        }

        return $attached;
    }
}

<?php

namespace App\Mail;

use App\Models\PendingPhotoReplacement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the submitting member when their photograph replacement request
 * has been approved (and published) or rejected.
 */
class PhotoReplacementDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PendingPhotoReplacement $request,
        public bool $approved,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->approved
                ? 'Jannayaks — Your replacement photograph has been published'
                : 'Jannayaks — Your replacement photograph request',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.photo-replacement-decision',
        );
    }
}

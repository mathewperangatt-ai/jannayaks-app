<?php

namespace App\Mail;

use App\Models\Profile;
use App\Models\ProfileContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProfileContactMessage $message,
        public Profile $profile,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A visitor would like to contact you — '.$this->profile->display_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.profile-contact',
            with: [
                'notificationText' => $this->message->ownerNotificationText(),
                'profileName' => (string) ($this->profile->display_name ?: $this->profile->full_name),
            ],
        );
    }
}

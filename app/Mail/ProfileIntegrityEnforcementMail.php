<?php

namespace App\Mail;

use App\Models\IntegrityIncident;
use App\Models\Profile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Immediate security-administrator alert: a confirmed integrity incident has
 * triggered enforcement (public suspension) for one profile.
 */
class ProfileIntegrityEnforcementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<IntegrityIncident>  $incidents
     */
    public function __construct(
        public Profile $profile,
        public array $incidents,
        public string $runId,
    ) {}

    public function envelope(): Envelope
    {
        $slug = $this->profile->slug !== null ? (string) $this->profile->slug : ('#'.$this->profile->id);

        return new Envelope(
            subject: '[JANNAYAKS SECURITY] Profile suspended — integrity incident on '.$slug,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.profile-integrity-enforcement',
        );
    }
}

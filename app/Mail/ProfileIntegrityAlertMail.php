<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Founder alert after a report-only integrity watchdog run.
 * Body carries run summary and public profile identifiers only —
 * never applicant contact details or full editorial text.
 */
class ProfileIntegrityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *   run_id: string,
     *   mode: string,
     *   scanned: int,
     *   profiles_published: int,
     *   healthy: int,
     *   authorized_change: int,
     *   integrity_mismatch: int,
     *   missing_snapshot: int,
     *   discrepancies: int,
     *   scan_error: int,
     *   incidents_created: int,
     *   mass_mismatch_alert: bool,
     *   deep_verify_percent: int,
     *   duration_seconds: float|int,
     * }  $summary
     * @param  list<array{
     *   profile_id: int,
     *   slug: string|null,
     *   type: string,
     *   detection_reason: string,
     *   newly_created: bool,
     * }>  $findings
     */
    public function __construct(
        public array $summary,
        public array $findings,
    ) {}

    public function envelope(): Envelope
    {
        $runTail = substr((string) ($this->summary['run_id'] ?? ''), -8);
        $mass = ! empty($this->summary['mass_mismatch_alert']);
        $prefix = $mass ? 'MASS MISMATCH — ' : '';

        return new Envelope(
            subject: $prefix.'Jannayaks integrity monitor alert (run …'.$runTail.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.profile-integrity-alert',
            with: [
                'summary' => $this->summary,
                'findings' => $this->findings,
            ],
        );
    }
}

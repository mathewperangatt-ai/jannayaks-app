<?php

namespace App\Console\Commands;

use App\Models\IntegrityIncident;
use App\Models\ProfileIntegritySnapshot;
use App\Services\ProfileIntegrityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Independent integrity-evidence anchor (Wave 3, owner-approved).
 *
 * Emails a daily digest of the SIGNED integrity evidence to the owner's
 * separate contact email — outside the application database. The digest
 * contains NO secrets, NO profile content, NO names and NO email addresses:
 * only profile IDs, snapshot hashes, signature prefixes, verification times
 * and incident counts. Keeping the emails lets the owner detect any later
 * attempt to rewrite the trusted baseline without the signing secret, even
 * by an attacker who controls the database.
 */
class SendIntegrityEvidenceDigestCommand extends Command
{
    protected $signature = 'jannayaks:integrity-evidence-digest';

    protected $description = 'Email the daily signed-integrity-evidence digest to the independent anchor recipient (no content, no secrets).';

    public function handle(ProfileIntegrityService $integrity, \App\Services\StaffAuditLogger $audit): int
    {
        $recipient = trim((string) config(
            'jannayaks.security.integrity.photo_moderation.evidence_anchor_recipient',
            '',
        ));

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->warn('JANNAYAKS_INTEGRITY_EVIDENCE_ANCHOR_EMAIL is not configured — digest skipped.');

            return self::SUCCESS;
        }

        $snapshots = ProfileIntegritySnapshot::query()
            ->join('profiles', 'profiles.id', '=', 'profile_integrity_snapshots.profile_id')
            ->where('profiles.status', 'published')
            ->whereNull('profiles.unpublished_at')
            ->orderBy('profile_integrity_snapshots.profile_id')
            ->get([
                'profile_integrity_snapshots.profile_id',
                'profile_integrity_snapshots.snapshot_hash',
                'profile_integrity_snapshots.signature',
                'profile_integrity_snapshots.verified_at',
            ]);

        $openIncidents = (int) IntegrityIncident::query()->where('status', 'open')->count();
        $newToday = (int) IntegrityIncident::query()->where('created_at', '>=', now()->subDay())->count();
        $unsignedCount = $snapshots->filter(fn ($row) => $row->signature === null)->count();

        // Rolling aggregate: chains today's rows onto yesterday's anchor so a
        // silently rewritten corpus cannot reproduce the same digest.
        $yesterday = trim((string) cache()->get('integrity.digest.aggregate', ''));

        $aggregate = hash('sha256', implode('|', array_merge(
            $yesterday === '' ? [] : [$yesterday],
            $snapshots->map(fn ($row): string => $row->profile_id.':'.$row->snapshot_hash.':'.$row->signature)->all(),
        )));

        cache()->put('integrity.digest.aggregate', $aggregate, now()->addDays(2));

        $digest = [
            'generated_at_ist' => now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
            'snapshots_signed' => $snapshots->count() - $unsignedCount,
            'snapshots_unsigned_legacy' => $unsignedCount,
            'open_incidents' => $openIncidents,
            'incidents_last_24h' => $newToday,
            'rolling_aggregate' => $aggregate,
            'rows' => $snapshots->map(fn ($row): array => [
                'profile_id' => (int) $row->profile_id,
                'snapshot_hash' => substr((string) $row->snapshot_hash, 0, 16),
                'signature' => substr((string) $row->signature, 0, 16),
                'verified_at_ist' => $row->verified_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ])->all(),
        ];

        $bodyText = "Jannayaks integrity-evidence digest — {$digest['generated_at_ist']} IST\n\n"
            ."Signed snapshots   : {$digest['snapshots_signed']}\n"
            ."Legacy unsigned    : {$digest['snapshots_unsigned_legacy']}\n"
            ."Open incidents     : {$digest['open_incidents']}\n"
            ."Incidents last 24h : {$digest['incidents_last_24h']}\n"
            ."Rolling aggregate  : {$digest['rolling_aggregate']}\n\n"
            ."Per-profile evidence (profile_id | snapshot hash prefix | signature prefix | verified at):\n"
            .collect($digest['rows'])
                ->map(fn (array $r): string => "  #{$r['profile_id']} | {$r['snapshot_hash']} | {$r['signature']} | {$r['verified_at_ist']}")
                ->implode("\n")
            ."\n\nKeep this email: comparing successive digests detects any attempt to rewrite\n"
            ."the trusted integrity baseline. This digest contains no profile content,\n"
            ."no personal data and no secrets.\n";

        Mail::to($recipient)->send(new \App\Mail\IntegrityEvidenceDigestMail($digest, $bodyText));

        $audit->log(
            action: 'integrity.evidence_digest_sent',
            after: [
                'snapshots' => $snapshots->count(),
                'open_incidents' => $openIncidents,
                'aggregate' => $aggregate,
            ],
            actor: null,
        );

        $this->info('Integrity evidence digest emailed to the anchor recipient ('.$snapshots->count().' snapshots).');

        return self::SUCCESS;
    }
}

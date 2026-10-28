<?php

namespace App\Console\Commands;

use App\Models\IntegrityIncident;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Services\ProfileIntegrityAlertNotifier;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileUrlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Published Profile Integrity Monitor — REPORT-ONLY watchdog.
 *
 * Never suspends, never modifies profile/photo/content data; only snapshots
 * (known-good baselines via authorised workflow or --establish-baseline) and
 * incidents (append-only evidence) are written.
 * INFRASTRUCTURE FAILURE IS A SCAN ERROR, never an integrity violation.
 *
 * Initial baselines for profiles that lack a snapshot are NOT created by the
 * scheduled scan. Use --establish-baseline with --profile= after reviewing
 * the live public state. Existing snapshots are never overwritten by that flag.
 *
 * After a scan, a founder summary email is sent only when this run created new
 * incident rows (IntegrityIncident.run_id = this run) or the mass-mismatch
 * threshold was exceeded. Re-detecting an unchanged open incident does not
 * create a new row (fingerprint dedupe) and alone does not email.
 */
class VerifyProfileIntegrityCommand extends Command
{
    protected $signature = 'jannayaks:verify-profile-integrity
                            {--profile= : Verify a single profile ID (testing/troubleshooting)}
                            {--deep-percent= : Override deep photo verification percentage (0-100)}
                            {--establish-baseline : Create a known-good snapshot for --profile= when none exists. Does not overwrite. Not used by the scheduler.}';

    protected $description = 'Verify published profiles against their known-good integrity snapshots (report-only).';

    public function handle(
        ProfileIntegrityService $integrity,
        ProfileUrlService $profileUrls,
        ProfileIntegrityAlertNotifier $alerts,
    ): int {
        if ((bool) $this->option('establish-baseline')) {
            return $this->establishBaselineForProfile($integrity, $profileUrls);
        }

        $runId = (string) Str::ulid();
        $startedAt = now();
        $mode = $this->integrityMode();

        $deepPercent = is_string($this->option('deep-percent')) && $this->option('deep-percent') !== ''
            ? max(0, min(100, (int) $this->option('deep-percent')))
            : max(0, min(100, (int) config(
                'jannayaks.security.integrity.deep_verify_percent',
                config('jannayaks.integrity.deep_verify_percent', 100),
            )));

        $counts = [
            'scanned' => 0,
            'healthy' => 0,
            'authorized_change' => 0,
            'integrity_mismatch' => 0,
            'missing_snapshot' => 0,
            'scan_error' => 0,
        ];
        $incidentsCreated = 0;
        $massMismatchAlert = false;
        /** @var list<array{profile_id: int, slug: string|null, type: string, detection_reason: string, newly_created: bool}> */
        $alertFindings = [];

        $query = Profile::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNull('unpublished_at')
            ->whereNull('suspended_at')
            ->whereNull('erasure_completed_at')
            ->orderBy('id');

        $targetId = $this->option('profile');
        if (is_string($targetId) && $targetId !== '') {
            $query->whereKey((int) $targetId);
        }

        $totalPublished = (clone $query)->toBase()->count();
        // Mass-mismatch alert threshold: surface a systemic warning without
        // stopping the scan. Every eligible profile is still examined.
        $alertThreshold = max(3, (int) ceil($totalPublished * 0.10));

        // Deterministic deep-verification rotation: stable per profile per run
        // day, so the whole corpus is covered over time when percent < 100.
        $runDay = (int) now()->format('z');

        try {
            $query->chunkById(100, function ($profiles) use (
                &$counts,
                &$incidentsCreated,
                &$massMismatchAlert,
                &$alertFindings,
                $integrity,
                $runId,
                $deepPercent,
                $runDay,
                $alertThreshold,
                $profileUrls,
            ): void {
                foreach ($profiles as $profile) {
                    if (! $profileUrls->isPubliclyVisible($profile)) {
                        continue; // Race with a concurrent suspension/unpublication.
                    }

                    $counts['scanned']++;

                    $deepVerify = $deepPercent >= 100
                        || ($deepPercent > 0 && ((($profile->id * 31 + $runDay) % 100) < $deepPercent));

                    try {
                        $result = $integrity->verifyProfile($profile, $runId, $deepVerify);
                    } catch (Throwable $e) {
                        $counts['scan_error']++;
                        Log::warning('profile-integrity: profile scan error', [
                            'run_id' => $runId,
                            'profile_id' => $profile->id,
                            'error' => mb_substr($e->getMessage(), 0, 300),
                        ]);

                        continue;
                    }

                    $classification = $result['classification'];
                    if ($classification === ProfileIntegrityService::CLASSIFICATION_MISSING_SNAPSHOT) {
                        $counts['missing_snapshot']++;
                    } elseif (isset($counts[$classification])) {
                        $counts[$classification]++;
                    } else {
                        $counts['scan_error']++;
                    }

                    foreach ($result['incidents'] as $incident) {
                        if (! $incident instanceof IntegrityIncident) {
                            continue;
                        }
                        $newlyCreated = (string) $incident->run_id === $runId;
                        if ($newlyCreated) {
                            $incidentsCreated++;
                        }
                        $alertFindings[] = [
                            'profile_id' => (int) $profile->id,
                            'slug' => $profile->slug !== null ? (string) $profile->slug : null,
                            'type' => (string) $incident->type,
                            'detection_reason' => mb_substr((string) $incident->detection_reason, 0, 240),
                            'newly_created' => $newlyCreated,
                        ];
                    }

                    $discrepancyCount = $counts['integrity_mismatch'] + $counts['missing_snapshot'];
                    if (! $massMismatchAlert && $discrepancyCount >= $alertThreshold) {
                        $massMismatchAlert = true;
                        Log::error('profile-integrity: MASS MISMATCH ALERT — discrepancy count reached threshold; scan continues for every remaining profile.', [
                            'run_id' => $runId,
                            'integrity_mismatch' => $counts['integrity_mismatch'],
                            'missing_snapshot' => $counts['missing_snapshot'],
                            'threshold' => $alertThreshold,
                            'scanned_so_far' => $counts['scanned'],
                        ]);
                    }
                }
            });
        } catch (Throwable $e) {
            Log::error('profile-integrity: run aborted by infrastructure failure', [
                'run_id' => $runId,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
            $this->error("Run {$runId} aborted by an infrastructure error — NOT treated as compromise. Partial counts follow.");
        }

        $durationSeconds = round(now()->diffInSeconds($startedAt), 1);
        $discrepancies = $counts['integrity_mismatch'] + $counts['missing_snapshot'];

        $summary = [
            'run_id' => $runId,
            'mode' => $mode,
            'profiles_published' => $totalPublished,
            'scanned' => $counts['scanned'],
            'healthy' => $counts['healthy'],
            'authorized_change' => $counts['authorized_change'],
            'integrity_mismatch' => $counts['integrity_mismatch'],
            'missing_snapshot' => $counts['missing_snapshot'],
            'discrepancies' => $discrepancies,
            'scan_error' => $counts['scan_error'],
            'incidents_created' => $incidentsCreated,
            'mass_mismatch_alert' => $massMismatchAlert,
            'deep_verify_percent' => $deepPercent,
            'duration_seconds' => $durationSeconds,
        ];

        Log::info('profile-integrity: watchdog run complete', $summary);
        $this->info("Integrity watchdog run {$runId} (mode={$mode}):");
        $this->table(array_keys($summary), [array_values($summary)]);

        if ($discrepancies > 0 || $massMismatchAlert) {
            // Report-only: surface for review; exit code stays SUCCESS so the
            // scheduler never treats a security finding as a job failure.
            $this->warn('Integrity findings recorded as incidents — REPORT-ONLY mode: nothing was suspended or modified.');
        }

        $alerts->notifyIfNeeded($summary, $alertFindings);

        return self::SUCCESS;
    }

    /**
     * Explicit, operator-driven baseline for one published profile that has no
     * snapshot. Requires --profile=. Never overwrites an existing snapshot.
     * Never suspends. Not invoked by the scheduler.
     */
    private function establishBaselineForProfile(
        ProfileIntegrityService $integrity,
        ProfileUrlService $profileUrls,
    ): int {
        $targetId = $this->option('profile');
        if (! is_string($targetId) || $targetId === '') {
            $this->error('--establish-baseline requires --profile=<id>.');

            return self::FAILURE;
        }

        $profile = Profile::query()->find((int) $targetId);
        if (! $profile instanceof Profile) {
            $this->error("Profile {$targetId} was not found.");

            return self::FAILURE;
        }

        if (! $profileUrls->isPubliclyVisible($profile)) {
            $this->error("Profile {$targetId} is not publicly visible; a baseline cannot be established.");

            return self::FAILURE;
        }

        if (ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->exists()) {
            $this->warn("Profile {$profile->id} already has an integrity snapshot. Existing baselines are never overwritten.");

            return self::SUCCESS;
        }

        $snapshot = $integrity->establishBaseline($profile, 'operator_establish_baseline');
        if ($snapshot === null) {
            $this->error("Could not establish a baseline for profile {$profile->id}.");

            return self::FAILURE;
        }

        $this->info("Baseline established for published profile {$profile->id} (snapshot #{$snapshot->id}, source_event=operator_establish_baseline).");
        $this->comment('Review the live public page before relying on this baseline. The scheduled scan does not create baselines.');

        return self::SUCCESS;
    }

    private function integrityMode(): string
    {
        return (string) config(
            'jannayaks.security.integrity.mode',
            config('jannayaks.integrity.mode', 'report'),
        );
    }
}

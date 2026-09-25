<?php

namespace App\Console\Commands;

use App\Models\Profile;
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
 * (known-good baselines) and incidents (append-only evidence) are written.
 * INFRASTRUCTURE FAILURE IS A SCAN ERROR, never an integrity violation.
 */
class VerifyProfileIntegrityCommand extends Command
{
    protected $signature = 'jannayaks:verify-profile-integrity
                            {--profile= : Verify a single profile ID (testing/troubleshooting)}
                            {--deep-percent= : Override deep photo verification percentage (0-100)}';

    protected $description = 'Verify published profiles against their known-good integrity snapshots (report-only).';

    public function handle(ProfileIntegrityService $integrity, ProfileUrlService $profileUrls): int
    {
        $runId = (string) Str::ulid();
        $startedAt = now();
        $mode = (string) config('jannayaks.integrity.mode', 'report');

        $deepPercent = is_string($this->option('deep-percent')) && $this->option('deep-percent') !== ''
            ? max(0, min(100, (int) $this->option('deep-percent')))
            : max(0, min(100, (int) config('jannayaks.integrity.deep_verify_percent', 100)));

        $counts = [
            'scanned' => 0,
            'healthy' => 0,
            'authorized_change' => 0,
            'integrity_mismatch' => 0,
            'scan_error' => 0,
            'bootstrap' => 0,
        ];
        $incidentsCreated = 0;
        $circuitBroken = false;
        $unscanned = 0;

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
        // Circuit breaker: a mass of suspected mismatches is almost certainly a
        // systemic issue, not mass compromise. max(3, 10%) matches the reviewed
        // design and stays conservative at every realistic scale.
        $breakerThreshold = max(3, (int) ceil($totalPublished * 0.10));

        // Deterministic deep-verification rotation: stable per profile per run
        // day, so the whole corpus is covered over time when percent < 100.
        $runDay = (int) now()->format('z');

        try {
            $query->chunkById(100, function ($profiles) use (&$counts, &$incidentsCreated, &$circuitBroken, &$unscanned, $integrity, $runId, $deepPercent, $runDay, $breakerThreshold, $profileUrls): void {
                foreach ($profiles as $profile) {
                    if ($circuitBroken) {
                        $unscanned++;

                        continue;
                    }

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

                    $counts[$result['classification']] = ($counts[$result['classification']] ?? 0) + 1;
                    $incidentsCreated += count($result['incidents']);

                    if ($result['classification'] === ProfileIntegrityService::CLASSIFICATION_MISMATCH
                        && $counts['integrity_mismatch'] >= $breakerThreshold) {
                        $circuitBroken = true;
                        Log::error('profile-integrity: CIRCUIT BREAKER tripped — suspected mismatches exceed threshold; remaining profiles left unscanned for administrator review.', [
                            'run_id' => $runId,
                            'mismatches' => $counts['integrity_mismatch'],
                            'threshold' => $breakerThreshold,
                            'published_total' => $counts['scanned'],
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

        $summary = [
            'run_id' => $runId,
            'mode' => $mode,
            'profiles_published' => $totalPublished,
            'scanned' => $counts['scanned'],
            'healthy' => $counts['healthy'],
            'authorized_change' => $counts['authorized_change'],
            'integrity_mismatch' => $counts['integrity_mismatch'],
            'scan_error' => $counts['scan_error'],
            'bootstrap' => $counts['bootstrap'],
            'incidents_created' => $incidentsCreated,
            'circuit_broken' => $circuitBroken,
            'unscanned_after_breaker' => $unscanned,
            'deep_verify_percent' => $deepPercent,
            'duration_seconds' => $durationSeconds,
        ];

        Log::info('profile-integrity: watchdog run complete', $summary);
        $this->info("Integrity watchdog run {$runId} (mode={$mode}):");
        $this->table(array_keys($summary), [array_values($summary)]);

        if ($counts['integrity_mismatch'] > 0 || $circuitBroken) {
            // Report-only: surface for review; exit code stays SUCCESS so the
            // scheduler never treats a security finding as a job failure.
            $this->warn('Integrity findings recorded as incidents — REPORT-ONLY mode: nothing was suspended or modified.');
        }

        return self::SUCCESS;
    }
}

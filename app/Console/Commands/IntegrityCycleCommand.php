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
 * Fast-cycle integrity verification (Wave 3).
 *
 * Closes the gap between application-level event triggers and the 4-hour
 * watchdog for changes that bypass the application (direct database edits,
 * direct R2 object writes):
 *
 *   --scope=content  every 5 minutes — canonical public-state diff vs the
 *                    trusted snapshot (no photo hashing). Catches direct
 *                    database tampering within minutes.
 *   --scope=photos   every 15 minutes — full streamed SHA-256 verification of
 *                    primary photograph objects. Catches storage-level
 *                    replacement/deletion.
 *
 * Both scopes run the SAME verifyProfile() reconciliation as the watchdog
 * (authorised audit events explain legitimate changes), and both apply
 * enforcement — suspend + notify — only on confirmed integrity_mismatch
 * while integrity mode is "enforce". Infrastructure failures are scan_error
 * and NEVER suspend.
 *
 * The 4-hour watchdog remains unchanged as an independent backup scan.
 */
class IntegrityCycleCommand extends Command
{
    protected $signature = 'jannayaks:integrity-cycle
                            {--scope=content : content (5-min) | photos (15-min)}
                            {--profile= : Restrict to one profile id}';

    protected $description = 'Fast-cycle integrity verification (content diff or photo hash) with enforce-mode response.';

    public function handle(ProfileIntegrityService $integrity, ProfileUrlService $profileUrls): int
    {
        $scope = (string) $this->option('scope');
        if (! in_array($scope, ['content', 'photos'], true)) {
            $this->error('--scope must be "content" or "photos".');

            return self::FAILURE;
        }

        $deep = $scope === 'photos';
        // run_id is varchar(32) in integrity_incidents — keep within bounds.
        $runId = ($scope === 'photos' ? 'cyc-photos-' : 'cyc-content-').now()->format('ymdHis').'-'.substr(bin2hex(random_bytes(4)), 0, 5);
        $startedAt = now();

        $counts = ['scanned' => 0, 'healthy' => 0, 'authorized_change' => 0, 'integrity_mismatch' => 0, 'missing_snapshot' => 0, 'scan_error' => 0];
        $enforcements = ['enforced' => 0, 'suspended' => 0, 'notified' => 0];
        $incidentIds = [];

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

        try {
            $query->chunkById(100, function ($profiles) use (&$counts, &$enforcements, &$incidentIds, $integrity, $profileUrls, $runId, $deep): void {
                foreach ($profiles as $profile) {
                    if (! $profileUrls->isPubliclyVisible($profile)) {
                        continue; // Race with a concurrent suspension.
                    }

                    $counts['scanned']++;

                    try {
                        $result = $integrity->verifyProfile($profile, $runId, $deep);
                    } catch (Throwable $e) {
                        $counts['scan_error']++;
                        $chain = '';
                        $current = $e;
                        while ($current !== null) {
                            $chain .= get_class($current).': '.mb_substr($current->getMessage(), 0, 400).' @ '.
                                ($current->getFile() ?: '?').':'.($current->getLine() ?: 0)."\n";
                            $current = $current->getPrevious();
                        }
                        Log::warning('profile-integrity: cycle scan error', [
                            'run_id' => $runId,
                            'scope' => $this->option('scope'),
                            'profile_id' => $profile->id,
                            'error' => $chain,
                        ]);

                        continue;
                    }

                    $classification = $result['classification'];
                    if (isset($counts[$classification])) {
                        $counts[$classification]++;
                    } else {
                        $counts['scan_error']++;
                    }

                    foreach ($result['incidents'] ?? [] as $incident) {
                        $incidentIds[] = (int) $incident->id;
                    }

                    // Enforce mode only; report mode records incidents only.
                    $enforcement = $integrity->enforceConfirmedTampering($profile, $result, $runId);
                    if ($enforcement['enforced']) {
                        $enforcements['enforced']++;
                        $enforcements['suspended'] += $enforcement['suspended'] ? 1 : 0;
                        $enforcements['notified'] += $enforcement['notified'] ? 1 : 0;
                        Log::warning('profile-integrity: cycle enforcement executed', [
                            'run_id' => $runId,
                            'scope' => $this->option('scope'),
                            'profile_id' => $profile->id,
                            'incident_ids' => $enforcement['incident_ids'],
                        ]);
                    }
                }
            });
        } catch (Throwable $e) {
            Log::error('profile-integrity: cycle aborted by infrastructure failure — NOT treated as compromise', [
                'run_id' => $runId,
                'scope' => $scope,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
            $this->error("Run {$runId} aborted by an infrastructure error.");

            return self::FAILURE;
        }

        $summary = [
            'run_id' => $runId,
            'scope' => $scope,
            'scanned' => $counts['scanned'],
            'healthy' => $counts['healthy'],
            'authorized_change' => $counts['authorized_change'],
            'integrity_mismatch' => $counts['integrity_mismatch'],
            'missing_snapshot' => $counts['missing_snapshot'],
            'scan_error' => $counts['scan_error'],
            'enforced' => $enforcements['enforced'],
            'suspended' => $enforcements['suspended'],
            'notified' => $enforcements['notified'],
            'incident_ids' => $incidentIds,
            'mode' => (string) config('jannayaks.security.integrity.mode', 'report'),
            'duration_seconds' => round(now()->diffInSeconds($startedAt), 1),
        ];

        Log::info('profile-integrity: cycle complete', $summary);
        $this->info(json_encode($summary));

        return self::SUCCESS;
    }
}

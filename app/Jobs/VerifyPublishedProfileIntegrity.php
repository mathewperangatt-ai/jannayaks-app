<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\ProfileIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Event-driven integrity verification for one published profile.
 *
 * Dispatched after commit by model observers whenever a published profile's
 * public-state inputs are saved through the application. Runs the SAME
 * verifyProfile() logic as the periodic watchdog (audit reconciliation
 * included), then applies enforcement (suspend + notify) only on a confirmed
 * integrity_mismatch while integrity mode is "enforce".
 *
 * Reliability: dispatched onto the database queue (existing worker); afterCommit
 * guarantees the triggering writes are visible. If the worker is down, jobs
 * accumulate in the jobs table and drain on recovery — nothing is lost.
 * verifyProfile() never classifies infrastructure failure as tampering.
 */
class VerifyPublishedProfileIntegrity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Serialised model; refresh from the database inside handle(). */
    public function __construct(
        public int $profileId,
        public string $runId,
    ) {
        $this->afterCommit = true;
    }

    public function handle(ProfileIntegrityService $integrity): void
    {
        $profile = Profile::query()->find($this->profileId);
        if ($profile === null) {
            return;
        }

        // Only live profiles are guarded by public suspension; hidden or
        // suspended profiles are handled by the periodic watchdog instead.
        if (! app(\App\Services\ProfileUrlService::class)->isPubliclyVisible($profile)) {
            return;
        }

        $result = $integrity->verifyProfile($profile, $this->runId, false);

        $enforcement = $integrity->enforceConfirmedTampering($profile, $result, $this->runId);

        if ($enforcement['enforced']) {
            Log::warning('profile-integrity: enforcement executed from event-driven check', [
                'run_id' => $this->runId,
                'profile_id' => $this->profileId,
                'classification' => $result['classification'],
                'incident_ids' => $enforcement['incident_ids'],
                'suspended' => $enforcement['suspended'],
                'notified' => $enforcement['notified'],
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('profile-integrity: event-driven verification job failed (infrastructure, not treated as tampering)', [
            'profile_id' => $this->profileId,
            'run_id' => $this->runId,
            'error' => mb_substr($exception->getMessage(), 0, 300),
        ]);
    }
}

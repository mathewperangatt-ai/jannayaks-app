<?php

namespace App\Jobs;

use App\Models\PhotoEnhancementRun;
use App\Services\PhotoEnhancementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued AI photo enhancement (database queue). Failures are recorded on
 * the run and never propagate to the customer upload workflow — the job
 * itself always resolves without retrying (retries are user-driven via
 * Regenerate/Retry so provenance and candidate cleanup stay predictable).
 */
class EnhanceProfilePhoto implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $runId) {}

    public function handle(PhotoEnhancementService $service): void
    {
        $run = PhotoEnhancementRun::query()->find($this->runId);
        if ($run === null) {
            return; // run row gone — nothing to do
        }

        $service->processRun($run);
    }

    public function failed(Throwable $e): void
    {
        // Belt-and-braces: processRun already catches, but if the queue
        // itself failed before/outside handling, still mark the run.
        $run = PhotoEnhancementRun::query()->find($this->runId);
        if ($run instanceof PhotoEnhancementRun && $run->isActive()) {
            $run->forceFill([
                'status' => PhotoEnhancementRun::STATUS_FAILED,
                'error_message' => mb_substr('Queue failure: '.$e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ])->save();
        }
    }
}

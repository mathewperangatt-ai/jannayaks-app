<?php

namespace App\Console\Commands;

use App\Models\AiEditorialRun;
use App\Services\StaffAuditLogger;
use Illuminate\Console\Command;

class FailStaleAiEditorialRunsCommand extends Command
{
    protected $signature = 'jannayaks:fail-stale-ai-runs {--minutes= : Override the staleness threshold in minutes}';

    protected $description = 'Mark AI editorial runs stuck in "running" as failed so generation can be retried.';

    public function handle(StaffAuditLogger $auditLogger): int
    {
        $minutes = $this->stalenessMinutes();
        $cutoff = now()->subMinutes($minutes);

        $stale = AiEditorialRun::query()
            ->where('status', AiEditorialRun::STATUS_RUNNING)
            ->whereRaw('COALESCE(started_at, created_at) < ?', [$cutoff])
            ->orderBy('id')
            ->get();

        foreach ($stale as $run) {
            $run->forceFill([
                'status' => AiEditorialRun::STATUS_FAILED,
                'error_code' => 'stale_run_recovered',
                'error_message' => "Run exceeded the {$minutes} minute staleness limit (process likely died mid-generation); marked failed so generation can be retried.",
                'finished_at' => now(),
            ])->save();

            $auditLogger->log(
                action: 'ai_editorial_run.stale_recovered',
                subject: $run,
                before: ['status' => AiEditorialRun::STATUS_RUNNING],
                after: [
                    'status' => AiEditorialRun::STATUS_FAILED,
                    'error_code' => 'stale_run_recovered',
                ],
                actor: null,
            );

            $this->warn("Marked AI editorial run #{$run->id} (application #{$run->application_id}) as failed after {$minutes} stale minutes.");
        }

        if ($stale->isEmpty()) {
            $this->info('No stale AI editorial runs found.');
        }

        return self::SUCCESS;
    }

    private function stalenessMinutes(): int
    {
        $override = $this->option('minutes');
        if (is_string($override) && $override !== '' && is_numeric($override)) {
            return max(1, (int) $override);
        }

        // Default: two full generation timeouts plus headroom, minimum 15 minutes.
        $timeoutSeconds = max(30, (int) config('jannayaks.ai.openai.timeout_seconds', 60));

        return max(15, (int) ceil(($timeoutSeconds * 2) / 60) + 5);
    }
}

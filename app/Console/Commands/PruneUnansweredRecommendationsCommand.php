<?php

namespace App\Console\Commands;

use App\Models\Recommendation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneUnansweredRecommendationsCommand extends Command
{
    public const RETENTION_DAYS = 30;

    protected $signature = 'jannayaks:prune-unanswered-recommendations';

    protected $description = 'Delete recommendation invitations that received no response within 30 days, including the invited person\'s email address.';

    public function handle(): int
    {
        $ids = Recommendation::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No unanswered recommendations were due for deletion.');

            return self::SUCCESS;
        }

        Recommendation::query()->whereIn('id', $ids->all())->delete();

        Log::info('recommendation: unanswered invitations deleted', [
            'recommendation_ids' => $ids->all(),
            'retention_days' => self::RETENTION_DAYS,
        ]);

        $this->info('Deleted '.$ids->count().' unanswered recommendation'.($ids->count() === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}

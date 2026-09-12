<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InterviewAnswer;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Support\Facades\DB;

class OnlineInterviewService
{
    public function loadAnswersMap(Application $application): array
    {
        $rows = InterviewAnswer::query()
            ->where('application_id', $application->id)
            ->get();

        $answers = [];
        foreach ($rows as $row) {
            $answers[(string) $row->question_id] = (string) $row->original_answer;
        }

        return $answers;
    }

    public function saveAnswers(Application $application, array $answers): array
    {
        $allowedIds = OnlineInterviewCatalog::idsForTier($application->package_tier);
        $allowedLookup = array_fill_keys($allowedIds, true);

        $updated = 0;
        $answers = is_array($answers) ? $answers : [];

        foreach ($answers as $qid => $raw) {
            $qid = (string) $qid;
            if (! isset($allowedLookup[$qid])) {
                continue;
            }
            $value = is_scalar($raw) ? trim((string) $raw) : '';

            $changed = DB::table('interview_answers')->upsert(
                [
                    'application_id'  => $application->id,
                    'user_id'         => (int) $application->user_id,
                    'question_id'     => $qid,
                    'original_answer' => $value === '' ? null : $value,
                    'answered_at'     => $value === '' ? null : now(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ],
                ['application_id', 'question_id'],
                ['original_answer', 'answered_at', 'updated_at']
            );

            $updated += (int) $changed;
        }

        return ['saved' => $updated] + OnlineInterviewCatalog::progress($application->package_tier, $this->loadAnswersMap($application));
    }
}

<?php

namespace App\Services;

use App\Contracts\EditorialAiClient;
use App\Models\AiEditorialRun;
use App\Models\Application;
use App\Models\EditorialClaimTrace;
use App\Models\EditorialContent;
use App\Models\InterviewAnswer;
use App\Models\Profile;
use App\Models\User;
use App\Support\EditorialPlainTextSanitizer;
use App\Support\EditorialSourcePayloadBuilder;
use App\Support\EditorialSystemPrompts;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class EditorialGenerationService
{
    public function __construct(
        private EditorialAiClient $ai,
        private EditorialSourcePayloadBuilder $payloadBuilder,
        private StaffAuditLogger $auditLogger,
        private EditorialPlainTextSanitizer $sanitizer,
    ) {}

    public function generateForApplication(Application $application, User $actor): AiEditorialRun
    {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial staff may generate AI drafts.');
        }

        if ($application->package_tier === 'in_memoriam') {
            throw new InvalidArgumentException('In Memoriam applications are excluded from the living-profile AI editorial pipeline.');
        }

        $lockSeconds = max(30, (int) config('jannayaks.ai.openai.timeout_seconds', 60) * 2);
        $lock = Cache::lock($this->lockKey($application), $lockSeconds);

        if (! $lock->get()) {
            throw new InvalidArgumentException('An AI editorial generation is already in progress for this application.');
        }

        try {
            return $this->runGeneration($application, $actor);
        } finally {
            $lock->release();
        }
    }

    private function runGeneration(Application $application, User $actor): AiEditorialRun
    {
        if (AiEditorialRun::query()
            ->where('application_id', $application->id)
            ->where('status', AiEditorialRun::STATUS_RUNNING)
            ->exists()) {
            throw new InvalidArgumentException('An AI editorial generation is already in progress for this application.');
        }

        $payload = $this->payloadBuilder->build($application);
        $fingerprint = $this->payloadBuilder->fingerprint($payload);

        try {
            $run = AiEditorialRun::query()->create([
                'application_id' => $application->id,
                'requested_by_user_id' => $actor->id,
                'provider' => $this->ai->providerName(),
                'model' => $this->ai->modelName(),
                'status' => AiEditorialRun::STATUS_RUNNING,
                'stage' => AiEditorialRun::STAGE_ENGLISH,
                'package_tier' => $application->package_tier,
                'input_fingerprint' => $fingerprint,
                'started_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueRunningViolation($e)) {
                throw new InvalidArgumentException('An AI editorial generation is already in progress for this application.');
            }

            throw $e;
        }

        try {
            $profile = $this->ensureDraftProfile($application);
            $run->forceFill(['profile_id' => $profile->id])->save();

            if ($application->status === Application::STATUS_AWAITING_EDITORIAL_REVIEW) {
                app(ApplicationWorkflowService::class)->updateStaffFields(
                    $application,
                    ['status' => Application::STATUS_IN_EDITORIAL_REVIEW],
                    $actor,
                );
            }

            $englishResult = $this->ai->generateEditorial([
                'system' => EditorialSystemPrompts::englishMaster(),
                'user' => $this->payloadBuilder->toDelimitedUserMessage($payload),
                'purpose' => 'english',
            ]);

            $english = $this->storeNewDraftVersion(
                profile: $profile,
                language: EditorialContent::LANGUAGE_EN,
                result: $englishResult,
                actor: $actor,
                run: $run,
                sourceEditorialId: null,
            );

            $run->forceFill([
                'english_editorial_content_id' => $english->id,
                'stage' => AiEditorialRun::STAGE_MALAYALAM,
            ])->save();

            $malayalam = null;
            $malayalamResult = null;
            if ((bool) config('jannayaks.ai.malayalam_pipeline_enabled', true)) {
                $mlPayload = [
                    'english_master' => [
                        'title' => $english->title,
                        'summary' => $english->summary,
                        'body' => $english->body,
                    ],
                    'data_boundary' => 'SOURCE_DATA_ONLY',
                ];

                $malayalamResult = $this->ai->generateEditorial([
                    'system' => EditorialSystemPrompts::malayalamAdaptation(),
                    'user' => $this->payloadBuilder->toDelimitedUserMessage($mlPayload),
                    'purpose' => 'malayalam',
                ]);

                $malayalam = $this->storeNewDraftVersion(
                    profile: $profile,
                    language: EditorialContent::LANGUAGE_ML,
                    result: $malayalamResult,
                    actor: $actor,
                    run: $run,
                    sourceEditorialId: $english->id,
                );

                $run->forceFill([
                    'malayalam_editorial_content_id' => $malayalam->id,
                    'stage' => AiEditorialRun::STAGE_CLAIMS,
                ])->save();
            }

            // Claim traces are internal QA metadata only — not independent verification.
            $this->storeClaimTraces($english, $englishResult['claims'] ?? [], $application);
            if ($malayalam && is_array($malayalamResult)) {
                $this->storeClaimTraces($malayalam, $malayalamResult['claims'] ?? [], $application);
            }

            $run->forceFill([
                'status' => AiEditorialRun::STATUS_SUCCEEDED,
                'stage' => AiEditorialRun::STAGE_COMPLETE,
                'finished_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.ai_generate_succeeded',
                subject: $run,
                after: [
                    'application_id' => $application->id,
                    'english_editorial_content_id' => $english->id,
                    'malayalam_editorial_content_id' => $malayalam?->id,
                ],
                actor: $actor,
            );

            return $run->fresh() ?? $run;
        } catch (Throwable $e) {
            Log::warning('editorial.ai.generation_failed', [
                'application_id' => $application->id,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $this->archiveIncompleteDraftsForFailedRun($run);

            $run->forceFill([
                'status' => AiEditorialRun::STATUS_FAILED,
                'error_code' => class_basename($e),
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.ai_generate_failed',
                subject: $run,
                after: [
                    'application_id' => $application->id,
                    'error_code' => $run->error_code,
                ],
                actor: $actor,
            );

            // Do not mark application editorially complete; do not destroy prior approved content.
            return $run->fresh() ?? $run;
        }
    }

    /**
     * Intermediate rows from a failed run must not appear as usable drafts.
     * Previously approved/generated content is never mutated here.
     */
    private function archiveIncompleteDraftsForFailedRun(AiEditorialRun $run): void
    {
        EditorialContent::query()
            ->where('generation_run_id', $run->id)
            ->where('status', EditorialContent::STATUS_DRAFT)
            ->where('ai_generated', true)
            ->update([
                'status' => EditorialContent::STATUS_ARCHIVED,
                'source_material' => 'Incomplete AI generation (run failed). Not a usable editorial draft. Linked to failed generation run; do not treat as current draft.',
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array{title: string, summary: string, body: string, claims?: list<array{excerpt: string, question_id: ?string}>}  $result
     */
    private function storeNewDraftVersion(
        Profile $profile,
        string $language,
        array $result,
        User $actor,
        AiEditorialRun $run,
        ?int $sourceEditorialId,
    ): EditorialContent {
        return DB::transaction(function () use ($profile, $language, $result, $actor, $run, $sourceEditorialId) {
            // Serialize version allocation for this profile (PostgreSQL-safe; aggregates cannot use FOR UPDATE).
            Profile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            $nextVersion = (int) EditorialContent::query()
                ->where('profile_id', $profile->id)
                ->where('language', $language)
                ->max('version_number');
            $nextVersion++;

            return EditorialContent::query()->create([
                'profile_id' => $profile->id,
                'source_editorial_content_id' => $sourceEditorialId,
                'generation_run_id' => $run->id,
                'language' => $language,
                'status' => EditorialContent::STATUS_DRAFT,
                'version_number' => $nextVersion,
                'title' => $this->sanitizer->sanitizeTitle((string) $result['title']),
                'summary' => $this->sanitizer->sanitizeSummary((string) $result['summary']),
                'body' => $this->sanitizer->sanitizeBody((string) $result['body']),
                'source_material' => 'AI draft from application source material. Treat as untrusted draft pending human review.',
                'ai_generated' => true,
                'created_by_id' => $actor->id,
            ]);
        });
    }

    /**
     * Store AI-reported claim excerpts for editorial QA.
     * mapped_to_source is true only when a real interview/source row was found.
     * Absence of a mapping must never be treated as verification of the claim.
     *
     * @param  list<array{excerpt: string, question_id: ?string}>  $claims
     */
    private function storeClaimTraces(EditorialContent $content, array $claims, Application $application): void
    {
        $sort = 0;
        foreach ($claims as $claim) {
            $excerpt = $this->sanitizer->sanitizeClaimExcerpt((string) ($claim['excerpt'] ?? ''));
            if ($excerpt === '') {
                continue;
            }

            $questionId = $claim['question_id'] ?? null;
            $answerId = null;
            if (is_string($questionId) && $questionId !== '') {
                $answerId = InterviewAnswer::query()
                    ->where('application_id', $application->id)
                    ->where('question_id', $questionId)
                    ->value('id');
            } else {
                $questionId = null;
            }

            $mapped = $answerId !== null;

            EditorialClaimTrace::query()->create([
                'editorial_content_id' => $content->id,
                'sort_order' => $sort++,
                'claim_excerpt' => $excerpt,
                'question_id' => is_string($questionId) ? $questionId : null,
                'interview_answer_id' => $answerId,
                'source_material_id' => null,
                'mapped_to_source' => $mapped,
            ]);
        }
    }

    private function ensureDraftProfile(Application $application): Profile
    {
        if ($application->profile_id) {
            $existing = Profile::query()->find($application->profile_id);
            if ($existing) {
                return $existing;
            }
        }

        $userId = (int) $application->user_id;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Application has no owning user for profile creation.');
        }

        $profile = Profile::query()->where('user_id', $userId)->first();
        if (! $profile) {
            $profile = Profile::query()->create([
                'user_id' => $userId,
                'status' => 'under_editorial_review',
                'full_name' => (string) ($application->full_name ?: 'Member'),
                'display_name' => $application->preferred_display_name,
                'profession' => '',
                'display_phone_consent' => false,
                'display_email_consent' => false,
            ]);
        }

        if (! $application->profile_id) {
            $application->forceFill(['profile_id' => $profile->id])->save();
        }

        return $profile;
    }

    private function lockKey(Application $application): string
    {
        return 'editorial-ai-generation:application:'.$application->id;
    }

    private function isUniqueRunningViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'ai_editorial_runs_one_running_per_application')
            || (str_contains($message, 'Unique violation') && str_contains($message, 'ai_editorial_runs'));
    }
}

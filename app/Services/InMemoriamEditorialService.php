<?php

namespace App\Services;

use App\Models\InMemoriamEditorialContent;
use App\Models\InMemoriamProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InMemoriamEditorialService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private InMemoriamLifecycleService $lifecycle,
    ) {}

    /**
     * @param  array{title?: string, body?: string, summary?: string, source_material?: string, status?: string}  $data
     *
     * @throws ValidationException
     */
    public function upsertHumanContent(
        InMemoriamProfile $profile,
        User $actor,
        string $language,
        array $data,
    ): InMemoriamEditorialContent {
        $this->lifecycle->assertEditableByStaff($profile, $actor);

        $language = strtolower(trim($language));
        if (! in_array($language, [InMemoriamEditorialContent::LANGUAGE_EN, InMemoriamEditorialContent::LANGUAGE_ML], true)) {
            throw ValidationException::withMessages([
                'language' => 'Memorial language must be English or Malayalam.',
            ]);
        }

        $status = (string) ($data['status'] ?? InMemoriamEditorialContent::STATUS_APPROVED);
        if (! in_array($status, [
            InMemoriamEditorialContent::STATUS_DRAFT,
            InMemoriamEditorialContent::STATUS_APPROVED,
            InMemoriamEditorialContent::STATUS_ARCHIVED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid editorial status.',
            ]);
        }

        return DB::transaction(function () use ($profile, $actor, $language, $data, $status): InMemoriamEditorialContent {
            $existing = InMemoriamEditorialContent::query()
                ->where('in_memoriam_profile_id', $profile->id)
                ->where('language', $language)
                ->where('status', '!=', InMemoriamEditorialContent::STATUS_ARCHIVED)
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            $payload = [
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['body'] ?? ''),
                'summary' => (string) ($data['summary'] ?? ''),
                'source_material' => (string) ($data['source_material'] ?? ''),
                'status' => $status,
                'ai_generated' => false,
            ];

            if ($existing instanceof InMemoriamEditorialContent) {
                $before = $existing->only(['title', 'status', 'ai_generated']);
                $existing->forceFill($payload + [
                    'reviewed_by_id' => $status === InMemoriamEditorialContent::STATUS_APPROVED
                        ? $actor->id
                        : $existing->reviewed_by_id,
                ])->save();

                $this->audit->log(
                    action: 'in_memoriam.editorial_updated',
                    subject: $existing,
                    before: $before,
                    after: $existing->only(['language', 'status', 'ai_generated']),
                    actor: $actor,
                );

                return $existing->fresh() ?? $existing;
            }

            $version = (int) InMemoriamEditorialContent::query()
                ->where('in_memoriam_profile_id', $profile->id)
                ->where('language', $language)
                ->max('version_number');

            $content = InMemoriamEditorialContent::query()->create($payload + [
                'in_memoriam_profile_id' => $profile->id,
                'language' => $language,
                'version_number' => $version + 1,
                'created_by_id' => $actor->id,
                'reviewed_by_id' => $status === InMemoriamEditorialContent::STATUS_APPROVED ? $actor->id : null,
            ]);

            $this->audit->log(
                action: 'in_memoriam.editorial_created',
                subject: $content,
                before: null,
                after: $content->only(['language', 'status', 'ai_generated', 'version_number']),
                actor: $actor,
            );

            return $content;
        });
    }
}

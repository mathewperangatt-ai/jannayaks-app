<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keeps previewed/approved editorial narrative versions immutable.
 * Narrative edits to locked rows create a new version instead of mutating history.
 */
class EditorialContentVersioningService
{
    public function __construct(
        private CustomerEditorialWorkflowService $customerWorkflow,
        private StaffAuditLogger $auditLogger,
    ) {}

    public function isNarrativeImmutable(EditorialContent $content): bool
    {
        if (in_array($content->status, [
            EditorialContent::STATUS_APPROVED,
            EditorialContent::STATUS_ARCHIVED,
        ], true)) {
            return true;
        }

        return Application::query()
            ->where(function ($query) use ($content) {
                $query->where('preview_english_editorial_content_id', $content->id)
                    ->orWhere('preview_malayalam_editorial_content_id', $content->id)
                    ->orWhere('customer_approved_english_editorial_content_id', $content->id);
            })
            ->exists();
    }

    /**
     * Apply staff editorial form data. Locked narrative rows get a successor version.
     *
     * $sourceEditorialContentId optionally establishes the EN↔ML adaptation
     * pairing on the CREATED successor at creation time (used by the
     * post-publication maintenance workflow so pairs are correct before any
     * customer visibility). Existing callers are unaffected.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyEditorUpdate(EditorialContent $content, array $data, User $actor, ?int $sourceEditorialContentId = null): EditorialContent
    {
        $narrativeChanging = $this->narrativeFieldsChanging($content, $data);
        $approvingInPlace = ($data['status'] ?? null) === EditorialContent::STATUS_APPROVED
            && ! ($this->isNarrativeImmutable($content) && $narrativeChanging);

        if ($content->language === EditorialContent::LANGUAGE_ML && $approvingInPlace && ! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only an authorised Admin may approve a Malayalam editorial version.');
        }

        if ($this->isNarrativeImmutable($content) && $narrativeChanging) {
            return $this->createSuccessorVersion($content, $data, $actor, $sourceEditorialContentId);
        }

        $this->assertMalayalamStatusChangeAllowed($content, $data, $actor);

        $content->fill($this->attributesForUpdate($content, $data, $actor))->save();

        return $content->fresh() ?? $content;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function narrativeFieldsChanging(EditorialContent $content, array $data): bool
    {
        foreach (['title', 'summary', 'body'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if ((string) $content->getAttribute($field) !== (string) $data[$field]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSuccessorVersion(EditorialContent $original, array $data, User $actor, ?int $sourceEditorialContentId = null): EditorialContent
    {
        return DB::transaction(function () use ($original, $data, $actor, $sourceEditorialContentId) {
            Application::query()->where('profile_id', $original->profile_id)->lockForUpdate()->first();
            Profile::query()->whereKey($original->profile_id)->lockForUpdate()->firstOrFail();

            $nextVersion = (int) EditorialContent::query()
                ->where('profile_id', $original->profile_id)
                ->where('language', $original->language)
                ->max('version_number');
            $nextVersion++;

            $status = EditorialContent::STATUS_DRAFT;

            $successor = EditorialContent::query()->create([
                'profile_id' => $original->profile_id,
                'source_editorial_content_id' => $sourceEditorialContentId ?? $original->source_editorial_content_id,
                'generation_run_id' => null,
                'language' => $original->language,
                'status' => $status,
                'version_number' => $nextVersion,
                'title' => (string) ($data['title'] ?? $original->title),
                'summary' => (string) ($data['summary'] ?? $original->summary),
                'body' => (string) ($data['body'] ?? $original->body),
                'source_material' => (string) ($data['source_material'] ?? 'Human editorial revision of immutable prior version '.$original->version_number.'.'),
                'ai_generated' => false,
                'created_by_id' => $actor->id,
                'reviewed_by_id' => null,
                'review_comment' => $data['review_comment'] ?? null,
            ]);

            // Original approved/previewed row stays intact; clear stale customer binding to it.
            $this->customerWorkflow->invalidateApprovalForEditorialContent(
                $original,
                $actor,
                'Editorial narrative revised via new version; prior preview/approved version preserved.',
            );

            $this->auditLogger->log(
                action: 'editorial_content.version_created_from_immutable',
                subject: $successor,
                before: [
                    'source_version_id' => $original->id,
                    'source_version_number' => $original->version_number,
                ],
                after: [
                    'id' => $successor->id,
                    'version_number' => $successor->version_number,
                    'status' => $successor->status,
                ],
                actor: $actor,
            );

            return $successor;
        });
    }

    /**
     * An editor may revise a Malayalam draft, but must not demote, archive,
     * or otherwise change the status of an approved or currently published
     * Malayalam row in place. Narrative edits of those rows already create
     * a draft successor and leave the live row untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertMalayalamStatusChangeAllowed(EditorialContent $content, array $data, User $actor): void
    {
        if ($content->language !== EditorialContent::LANGUAGE_ML || $actor->isAdmin() || ! array_key_exists('status', $data)) {
            return;
        }

        if ((string) $data['status'] === (string) $content->status) {
            return;
        }

        if ($content->status !== EditorialContent::STATUS_APPROVED && ! $this->isCurrentPublishedMalayalam($content)) {
            return;
        }

        throw new InvalidArgumentException('Only an authorised Admin may change the status of an approved or published Malayalam version.');
    }

    private function isCurrentPublishedMalayalam(EditorialContent $content): bool
    {
        return Application::query()
            ->where('published_malayalam_editorial_content_id', $content->id)
            ->exists();
    }

    /**
     * Admin create must not open an approved row. Approval stays on the
     * review action so a new record cannot replace a published version.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attributesForNewDraft(array $data): array
    {
        if (($data['status'] ?? EditorialContent::STATUS_DRAFT) !== EditorialContent::STATUS_DRAFT) {
            throw new InvalidArgumentException('New editorial records must be created as drafts.');
        }

        if (! Profile::query()->whereKey($data['profile_id'] ?? 0)->exists()) {
            throw new InvalidArgumentException('Editorial content must belong to an existing profile.');
        }

        $data['status'] = EditorialContent::STATUS_DRAFT;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesForUpdate(EditorialContent $content, array $data, User $actor): array
    {
        $attributes = [
            'title' => $data['title'] ?? $content->title,
            'summary' => $data['summary'] ?? $content->summary,
            'body' => $data['body'] ?? $content->body,
            'source_material' => $data['source_material'] ?? $content->source_material,
            'status' => $data['status'] ?? $content->status,
            'review_comment' => $data['review_comment'] ?? $content->review_comment,
            'ai_generated' => (bool) $content->ai_generated,
            'language' => $content->language,
            'version_number' => $content->version_number,
            'profile_id' => $content->profile_id,
        ];

        if (($attributes['status'] ?? null) === EditorialContent::STATUS_APPROVED) {
            $attributes['reviewed_by_id'] = $actor->id;
        }

        return $attributes;
    }
}

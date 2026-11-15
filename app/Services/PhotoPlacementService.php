<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialPhotoPlacement;
use App\Models\MediaItem;
use App\Models\PhotoPlacementRun;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * AI-assisted editorial photograph placement. Invoked ONLY through the
 * explicit Filament administrator action; identical inputs (photo set +
 * editorial version) reuse the previous succeeded run instead of paying
 * for repeated analysis. The AI recommends; administrators decide.
 *
 * The service never invents, generates, replaces or modifies photographs:
 * MediaItem rows and their assets are read-only here, and only placement
 * rows (positions, anchors, captions, approval state) are written.
 */
class PhotoPlacementService
{
    public function __construct(
        private \App\Contracts\PhotoPlacementAiClient $client,
        private StaffAuditLogger $audit,
    ) {}

    /**
     * @throws InvalidArgumentException|ValidationException
     */
    public function recommend(Application $application, User $actor): PhotoPlacementRun
    {
        if (! $actor->canManageEditorial() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only editorial administrators may request photograph placement recommendations.');
        }

        $profile = $application->profile;
        if (! $profile instanceof Profile) {
            throw new InvalidArgumentException('The application has no profile to place photographs on.');
        }

        $photos = app(PublicProfilePresentationService::class)
            ->publicProfilePhotos($profile->fresh() ?? $profile);
        if ($photos->isEmpty()) {
            throw new InvalidArgumentException('Approve at least one photograph before requesting placement recommendations.');
        }

        $english = $this->previewNarrative($application);
        if (! $english instanceof EditorialContent) {
            throw new InvalidArgumentException('Release an editorial preview before requesting placement recommendations.');
        }

        $fingerprint = $this->inputFingerprint($profile, $photos, $english);

        // Cost control: an unchanged photo set + editorial version reuses
        // the previous succeeded run — the AI is not invoked again.
        $reused = PhotoPlacementRun::query()
            ->where('application_id', $application->id)
            ->where('status', PhotoPlacementRun::STATUS_SUCCEEDED)
            ->where('input_fingerprint', $fingerprint)
            ->orderByDesc('id')
            ->first();
        if ($reused instanceof PhotoPlacementRun) {
            return $reused;
        }

        $lock = Cache::lock('photo-placement:application:'.$application->id, 120);
        if (! $lock->get()) {
            throw new InvalidArgumentException('A placement recommendation is already being processed. Please wait.');
        }

        try {
            $run = PhotoPlacementRun::query()->create([
                'application_id' => $application->id,
                'profile_id' => $profile->id,
                'status' => PhotoPlacementRun::STATUS_RUNNING,
                'provider' => $this->client->providerName(),
                'model' => $this->client->modelName(),
                'input_fingerprint' => $fingerprint,
                'english_editorial_content_id' => $english->id,
                'requested_by_user_id' => $actor->id,
                'started_at' => now(),
            ]);

            try {
                $payloadPhotos = $photos->map(fn (MediaItem $item): array => [
                    'media_id' => (int) $item->id,
                    'base64' => base64_encode((string) Storage::disk($item->disk ?: config('jannayaks.media.public_disk', 'public'))->get((string) $item->storage_path_key)),
                    'mime' => (string) ($item->mime_type ?: 'image/jpeg'),
                ])->values()->all();

                $narrative = trim((string) $english->summary."\n\n".(string) $english->body);
                $recommendations = $this->client->recommendPlacements($payloadPhotos, $narrative);

                [$warnings, $clean] = $this->separateInvalid($recommendations, $photos);

                DB::transaction(function () use ($application, $profile, $run, &$clean, &$warnings, $actor): void {
                    // A fresh recommendation replaces prior un-decided AI
                    // suggestions; approved placements are never overwritten.
                    EditorialPhotoPlacement::query()
                        ->where('profile_id', $profile->id)
                        ->where('status', EditorialPhotoPlacement::STATUS_SUGGESTED)
                        ->delete();

                    $approvedMediaIds = EditorialPhotoPlacement::query()
                        ->where('profile_id', $profile->id)
                        ->where('status', EditorialPhotoPlacement::STATUS_APPROVED)
                        ->pluck('media_item_id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    $clean = array_values(array_filter($clean, function (array $entry) use ($approvedMediaIds, &$warnings): bool {
                        if (in_array($entry['media_id'], $approvedMediaIds, true)) {
                            $warnings[] = 'Skipped a recommendation for media_id '.$entry['media_id'].' because an administrator-approved placement already exists.';
                            return false;
                        }
                        return true;
                    }));

                    foreach ($clean as $entry) {
                        EditorialPhotoPlacement::query()->updateOrCreate(
                            [
                                'profile_id' => $profile->id,
                                'media_item_id' => $entry['media_id'],
                            ],
                            [
                                'application_id' => $application->id,
                                'placement_type' => $entry['placement_type'],
                                'section_anchor' => $entry['section_anchor'],
                                'display_order' => $entry['display_order'],
                                'caption_en' => $entry['caption_en'],
                                'caption_ml' => $entry['caption_ml'],
                                'ai_rationale' => $entry['rationale'],
                                'status' => EditorialPhotoPlacement::STATUS_SUGGESTED,
                                'source' => EditorialPhotoPlacement::SOURCE_AI,
                                'placement_run_id' => $run->id,
                            ],
                        );
                    }
                });

                $run->forceFill([
                    'status' => PhotoPlacementRun::STATUS_SUCCEEDED,
                    'recommendations' => $clean,
                    'warnings' => $warnings,
                    'finished_at' => now(),
                ])->save();

                $this->audit->log(
                    action: 'media.photo_placement_recommended',
                    subject: $run,
                    before: null,
                    after: [
                        'application_id' => $application->id,
                        'profile_id' => $profile->id,
                        'recommendation_count' => count($clean),
                        'warning_count' => count($warnings),
                        'reused' => false,
                    ],
                    actor: $actor,
                );

                return $run;
            } catch (Throwable $e) {
                $run->forceFill([
                    'status' => PhotoPlacementRun::STATUS_FAILED,
                    'error_code' => class_basename($e),
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    'finished_at' => now(),
                ])->save();

                $this->audit->log(
                    action: 'media.photo_placement_failed',
                    subject: $run,
                    before: null,
                    after: [
                        'application_id' => $application->id,
                        'error_code' => class_basename($e),
                    ],
                    actor: $actor,
                );

                return $run;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Administrator decision: accept the recommendation as-is or override
     * its type, anchor, order and captions. Only the placement row changes.
     */
    public function decidePlacement(
        EditorialPhotoPlacement $placement,
        User $actor,
        string $placementType,
        string $anchor,
        int $displayOrder,
        ?string $captionEn = null,
        ?string $captionMl = null,
    ): EditorialPhotoPlacement {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial administrators may decide photograph placements.');
        }

        if (! in_array($placementType, EditorialPhotoPlacement::PLACEMENT_TYPES, true)) {
            throw new InvalidArgumentException('Unknown placement type.');
        }
        if (! in_array($anchor, EditorialPhotoPlacement::SECTION_ANCHORS, true)) {
            throw new InvalidArgumentException('Unknown section anchor.');
        }

        $placement->forceFill([
            'placement_type' => $placementType,
            'section_anchor' => $anchor,
            'display_order' => max(1, min(9, $displayOrder)),
            'caption_en' => $captionEn !== null && trim($captionEn) !== '' ? trim($captionEn) : null,
            'caption_ml' => $captionMl !== null && trim($captionMl) !== '' ? trim($captionMl) : null,
            'status' => EditorialPhotoPlacement::STATUS_APPROVED,
            'source' => EditorialPhotoPlacement::SOURCE_ADMIN,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
        ])->save();

        $this->audit->log(
            action: 'media.photo_placement_decided',
            subject: $placement,
            before: $placement->getOriginal(),
            after: [
                'profile_id' => $placement->profile_id,
                'media_item_id' => $placement->media_item_id,
                'placement_type' => $placementType,
                'section_anchor' => $anchor,
                'display_order' => $placement->display_order,
                'status' => EditorialPhotoPlacement::STATUS_APPROVED,
            ],
            actor: $actor,
        );

        return $placement->fresh() ?? $placement;
    }

    /**
     * Remove a placement. The underlying photograph, its approval state and
     * its stored asset are untouched.
     */
    public function removePlacement(EditorialPhotoPlacement $placement, User $actor): void
    {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial administrators may remove photograph placements.');
        }

        $after = [
            'profile_id' => $placement->profile_id,
            'media_item_id' => $placement->media_item_id,
            'placement_type' => $placement->placement_type,
        ];

        $placement->delete();

        $this->audit->log(
            action: 'media.photo_placement_removed',
            subject: null,
            before: $after,
            after: null,
            actor: $actor,
        );
    }

    /**
     * The approved placements for a profile, ordered hero → inline →
     * gallery, each carrying only publicly displayable media.
     *
     * @return \Illuminate\Support\Collection<int, EditorialPhotoPlacement>
     */
    public function approvedPlacements(Profile $profile): \Illuminate\Support\Collection
    {
        return EditorialPhotoPlacement::query()
            ->where('profile_id', $profile->id)
            ->where('status', EditorialPhotoPlacement::STATUS_APPROVED)
            ->with('mediaItem')
            ->get()
            ->filter(fn (EditorialPhotoPlacement $placement): bool => $placement->mediaItem !== null
                && $placement->mediaItem->isApprovedForPublicDisplay())
            ->sortBy(fn (EditorialPhotoPlacement $placement): array => [
                $placement->placement_type === EditorialPhotoPlacement::TYPE_HERO ? 0 : 1,
                $placement->section_anchor ?? '',
                $placement->display_order,
                $placement->id,
            ])
            ->values();
    }

    private function previewNarrative(Application $application): ?EditorialContent
    {
        if ($application->preview_english_editorial_content_id !== null) {
            $content = EditorialContent::query()->find($application->preview_english_editorial_content_id);
            if ($content !== null) {
                return $content;
            }
        }

        return EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MediaItem>  $photos
     */
    private function inputFingerprint(Profile $profile, \Illuminate\Support\Collection $photos, EditorialContent $english): string
    {
        $payload = [
            'profile' => $profile->id,
            'photos' => $photos->map(fn (MediaItem $item): array => [
                (int) $item->id,
                (string) $item->photo_sha256,
                (bool) $item->is_primary,
                (int) $item->display_order,
            ])->values()->all(),
            'editorial' => [(int) $english->id, (string) $english->updated_at],
        ];

        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * Split the raw AI response into valid recommendations and warnings.
     * Media references outside the analysed set and unknown placement types
     * are discarded — never stored.
     *
     * @param  \Illuminate\Support\Collection<int, MediaItem>  $photos
     * @return array{0: list<string>, 1: list<array{media_id: int, placement_type: string, section_anchor: ?string, display_order: int, caption_en: ?string, caption_ml: ?string, rationale: ?string}>}
     */
    private function separateInvalid(array $recommendations, \Illuminate\Support\Collection $photos): array
    {
        $validIds = $photos->map(fn (MediaItem $item): int => (int) $item->id)->all();
        $warnings = [];
        $clean = [];

        foreach ($recommendations as $entry) {
            $mediaId = (int) ($entry['media_id'] ?? 0);
            $type = strtolower((string) ($entry['placement_type'] ?? ''));
            if (! in_array($mediaId, $validIds, true)) {
                $warnings[] = 'Discarded a recommendation referencing media_id '.$mediaId.' which is not part of the analysed set.';
                continue;
            }
            if (! in_array($type, EditorialPhotoPlacement::PLACEMENT_TYPES, true)) {
                $warnings[] = 'Discarded a recommendation with unknown placement type "'.$type.'" for media_id '.$mediaId.'.';
                continue;
            }
            $anchor = strtolower((string) ($entry['section_anchor'] ?? ''));
            $clean[] = [
                'media_id' => $mediaId,
                'placement_type' => $type,
                'section_anchor' => in_array($anchor, EditorialPhotoPlacement::SECTION_ANCHORS, true) ? $anchor : null,
                'display_order' => max(1, min(9, (int) ($entry['display_order'] ?? 1))),
                'caption_en' => isset($entry['caption_en']) && is_string($entry['caption_en']) && trim($entry['caption_en']) !== '' ? mb_substr(trim($entry['caption_en']), 0, 300) : null,
                'caption_ml' => isset($entry['caption_ml']) && is_string($entry['caption_ml']) && trim($entry['caption_ml']) !== '' ? mb_substr(trim($entry['caption_ml']), 0, 300) : null,
                'rationale' => isset($entry['rationale']) && is_string($entry['rationale']) ? mb_substr(trim($entry['rationale']), 0, 500) : null,
            ];
        }

        return [$warnings, $clean];
    }
}

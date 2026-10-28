<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\IntegrityIncident;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\StaffActionLog;
use App\Services\ProfileUrlService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Published Profile Integrity Monitor — REPORT-ONLY implementation (Wave 2C).
 *
 * Establishes a known-good snapshot of each published profile's PUBLIC state
 * and verifies the live state against it. Detected differences are reconciled
 * against authorized staff audit events; unexplained differences become
 * integrity incidents (evidence only). This service NEVER suspends profiles
 * and NEVER modifies profile/photo/content data.
 */
class ProfileIntegrityService
{
    public const CLASSIFICATION_HEALTHY = 'healthy';

    public const CLASSIFICATION_AUTHORIZED_CHANGE = 'authorized_change';

    public const CLASSIFICATION_MISMATCH = 'integrity_mismatch';

    public const CLASSIFICATION_SCAN_ERROR = 'scan_error';

    /**
     * Published profile has no known-good snapshot. The watchdog records an
     * incident and does NOT adopt the live state as trusted. Operators must
     * establish a baseline through establishBaseline() / the command flag.
     */
    public const CLASSIFICATION_MISSING_SNAPSHOT = 'missing_snapshot';

    /**
     * @deprecated Kept for callers that still compare against the old label.
     * Missing snapshots no longer bootstrap; see CLASSIFICATION_MISSING_SNAPSHOT.
     */
    public const CLASSIFICATION_BOOTSTRAP = 'bootstrap';

    /**
     * Audit actions that can authorize a public-state change, by section.
     * An event only explains a transition when its payload/subject matches
     * the observed change (see sectionAuthorized()).
     */
    private const AUTHORIZED_ACTIONS = [
        'identity' => ['profile_url.slug_assigned', 'profile_url.slug_changed'],
        'editorial' => [
            'application.published',
            'application.published.replacement',
            'application.malayalam_published',
            'editorial_content.created',
            'editorial_content.updated',
            'editorial_content.version_created_from_immutable',
        ],
        'photos' => [
            'media.profile_photo_uploaded',
            'media.profile_photo_approved',
            'media.profile_photo_rejected',
            'media.profile_photo_primary_set',
            'media.profile_photo_deleted',
            'application.published.replacement',
        ],
        'public_offices' => [],
        'video_links' => [
            'media.external_video_submitted',
            'media.external_video_approved',
            'media.external_video_rejected',
            'media.external_video_change_requested',
        ],
    ];

    public function __construct(
        private ProfileUrlService $profileUrls,
        private PublicProfilePresentationService $presentation,
    ) {}

    /**
     * Deterministic canonical representation of the state the public
     * presentation layer would render. Mirrors PublicProfilePresentationService.
     *
     * @return array<string, mixed>
     */
    public function canonicalPublicState(Profile $profile): array
    {
        $profile = $profile->fresh([
            'application',
            'media',
            'publicOffices',
            'externalLinks',
        ]) ?? $profile;

        $english = $this->presentation->resolveApprovedEnglish($profile);
        $malayalam = $this->presentation->resolveApprovedMalayalam($profile, $english);

        $photos = $this->presentation->publicProfilePhotos($profile)->map(fn (MediaItem $item): array => [
            'media_id' => (int) $item->id,
            'disk' => (string) ($item->disk ?? ''),
            'key' => (string) ($item->storage_path_key ?? ''),
            'sha256' => $item->photo_sha256 !== null ? (string) $item->photo_sha256 : null,
            'size_bytes' => $item->size_bytes !== null ? (int) $item->size_bytes : null,
            'is_primary' => (bool) $item->is_primary,
        ])->values()->all();

        // publicProfilePhotos already sorts primary-first; the first entry is
        // exactly what the presentation layer renders as the portrait.
        $primary = $photos[0] ?? null;

        return [
            'slug' => $profile->slug !== null ? strtolower((string) $profile->slug) : null,
            'display_name' => $this->presentation->displayName($profile),
            'profession' => filled($profile->profession) ? (string) $profile->profession : null,
            'english_editorial_content_id' => $english?->id,
            'english_content_hash' => $english ? $this->contentHash($english) : null,
            'malayalam_editorial_content_id' => $malayalam?->id,
            'malayalam_content_hash' => $malayalam ? $this->contentHash($malayalam) : null,
            'primary_photo_media_id' => $primary['media_id'] ?? null,
            'primary_photo_disk' => $primary['disk'] ?? null,
            'primary_photo_key' => $primary['key'] ?? null,
            'primary_photo_sha256' => $primary['sha256'] ?? null,
            'primary_photo_size_bytes' => $primary['size_bytes'] ?? null,
            'photos' => $photos,
            'public_offices' => $this->canonicalOffices($profile),
            'video_links' => $this->canonicalVideoLinks($profile),
        ];
    }

    /**
     * Create or refresh the known-good snapshot for a published profile.
     * Must only be called when the profile is publicly visible; unpublished
     * profiles have no known-good PUBLIC state.
     *
     * Called from authorised workflow paths (publish, photo approval, etc.)
     * and from establishBaseline() for an explicit operator baseline.
     * The scheduled watchdog never uses this to invent a first baseline.
     */
    public function refreshSnapshot(Profile $profile, string $sourceEvent): ?ProfileIntegritySnapshot
    {
        $profile = $profile->fresh() ?? $profile;
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            return null;
        }

        $canonical = $this->canonicalPublicState($profile);

        return ProfileIntegritySnapshot::query()->updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'application_id' => $profile->application?->id,
                'slug' => $canonical['slug'],
                'display_name' => $canonical['display_name'],
                'profession' => $canonical['profession'],
                'english_editorial_content_id' => $canonical['english_editorial_content_id'],
                'english_content_hash' => $canonical['english_content_hash'],
                'malayalam_editorial_content_id' => $canonical['malayalam_editorial_content_id'],
                'malayalam_content_hash' => $canonical['malayalam_content_hash'],
                'primary_photo_media_id' => $canonical['primary_photo_media_id'],
                'primary_photo_disk' => $canonical['primary_photo_disk'],
                'primary_photo_key' => $canonical['primary_photo_key'],
                'primary_photo_sha256' => $canonical['primary_photo_sha256'],
                'primary_photo_size_bytes' => $canonical['primary_photo_size_bytes'],
                'photos' => $canonical['photos'],
                'public_offices' => $canonical['public_offices'],
                'video_links' => $canonical['video_links'],
                'snapshot_hash' => $this->payloadHash($canonical),
                'source_event' => $sourceEvent,
            ],
        );
    }

    /**
     * Explicit operator / authorised baseline for a published profile that has
     * no snapshot yet. Never overwrites an existing snapshot. Does not suspend
     * or alter public content. Intended for the artisan --establish-baseline
     * path and equivalent controlled recovery — not for the scheduled scan.
     */
    public function establishBaseline(Profile $profile, string $sourceEvent = 'operator_establish_baseline'): ?ProfileIntegritySnapshot
    {
        $profile = $profile->fresh() ?? $profile;
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            return null;
        }

        if (ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->exists()) {
            return null;
        }

        return $this->refreshSnapshot($profile, $sourceEvent);
    }

    /**
     * Verify one published profile against its snapshot.
     *
     * @return array{
     *   classification: string,
     *   incidents: list<IntegrityIncident>,
     *   snapshot_refreshed: bool,
     *   bootstrapped: bool,
     *   reasons: list<string>,
     *   event_ids: list<int>,
     * }
     */
    public function verifyProfile(Profile $profile, string $runId, bool $deepVerifyPhoto): array
    {
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first();

        if ($snapshot === null) {
            // Do NOT adopt the live public state as trusted. A missing snapshot
            // for a published profile is itself a discrepancy: either the
            // baseline was never established, or it was removed. Operators
            // establish a baseline only via establishBaseline().
            $current = $this->canonicalPublicState($profile);
            $incident = $this->recordIncident(
                $profile,
                $runId,
                IntegrityIncident::TYPE_MISSING_SNAPSHOT,
                'Published profile has no known-good integrity snapshot. Current public state was not accepted as the trusted baseline.',
                ['snapshot' => null],
                ['public_state' => $current],
            );

            return [
                'classification' => self::CLASSIFICATION_MISSING_SNAPSHOT,
                'incidents' => [$incident],
                'snapshot_refreshed' => false,
                'bootstrapped' => false,
                'reasons' => ['missing_snapshot'],
                'event_ids' => [],
            ];
        }

        $expected = $snapshot->canonicalPayload();
        $current = $this->canonicalPublicState($profile);

        $diffs = $this->sectionDiffs($expected, $current);

        if ($diffs === []) {
            // Reference state intact; verify the underlying photo object.
            $photoCheck = $this->verifyPrimaryPhotoObject(
                $current,
                $deepVerifyPhoto,
            );

            if ($photoCheck['classification'] === self::CLASSIFICATION_HEALTHY) {
                $snapshot->forceFill(['verified_at' => now()])->save();

                return [
                    'classification' => self::CLASSIFICATION_HEALTHY,
                    'incidents' => [],
                    'snapshot_refreshed' => false,
                    'bootstrapped' => false,
                    'reasons' => $photoCheck['reasons'],
                    'event_ids' => [],
                ];
            }

            if ($photoCheck['classification'] === self::CLASSIFICATION_SCAN_ERROR) {
                return [
                    'classification' => self::CLASSIFICATION_SCAN_ERROR,
                    'incidents' => [],
                    'snapshot_refreshed' => false,
                    'bootstrapped' => false,
                    'reasons' => $photoCheck['reasons'],
                    'event_ids' => [],
                ];
            }

            // Confirmed object substitution.
            $incident = $this->recordIncident(
                $profile,
                $runId,
                IntegrityIncident::TYPE_PHOTOGRAPH_OBJECT,
                $photoCheck['reasons'][0] ?? 'Stored photograph object does not match its known-good SHA-256.',
                ['primary_photo' => $this->primarySection($expected)],
                ['primary_photo' => $this->primarySection($current)],
            );

            return [
                'classification' => self::CLASSIFICATION_MISMATCH,
                'incidents' => [$incident],
                'snapshot_refreshed' => false,
                'bootstrapped' => false,
                'reasons' => $photoCheck['reasons'],
                'event_ids' => [],
            ];
        }

        // Differences detected: reconcile each section against audit events.
        $unexplained = [];
        $eventIds = [];
        foreach ($diffs as $section => $diff) {
            $explanation = $this->sectionAuthorized($profile, $snapshot, $section, $expected, $current);
            if ($explanation !== null) {
                $eventIds = array_merge($eventIds, $explanation);

                continue;
            }
            $unexplained[$section] = $diff;
        }

        if ($unexplained === []) {
            // Fully explained by authorized events: adopt as new known-good.
            $this->refreshSnapshot($profile, 'watchdog_reconciled');

            return [
                'classification' => self::CLASSIFICATION_AUTHORIZED_CHANGE,
                'incidents' => [],
                'snapshot_refreshed' => true,
                'bootstrapped' => false,
                'reasons' => array_keys($diffs),
                'event_ids' => $eventIds,
            ];
        }

        $incidents = [];
        foreach ($unexplained as $section => $diff) {
            $incidents[] = $this->recordIncident(
                $profile,
                $runId,
                $this->incidentTypeForSection($section),
                "Public {$section} state differs from the known-good snapshot without an authorizing audit event.",
                [$section => $diff['expected']],
                [$section => $diff['current']],
                $eventIds ?: null,
            );
        }

        return [
            'classification' => self::CLASSIFICATION_MISMATCH,
            'incidents' => $incidents,
            'snapshot_refreshed' => false,
            'bootstrapped' => false,
            'reasons' => array_keys($unexplained),
            'event_ids' => $eventIds,
        ];
    }

    /**
     * Cheap + deep verification of the stored photo object behind the current
     * primary reference. INFRASTRUCTURE FAILURE IS A SCAN ERROR, never a
     * violation: only a successfully-read object whose SHA-256 differs from
     * the known-good hash is a confirmed substitution.
     *
     * @param  array<string, mixed>  $canonical
     * @return array{classification: string, reasons: list<string>}
     */
    private function verifyPrimaryPhotoObject(array $canonical, bool $deepVerify): array
    {
        $key = (string) ($canonical['primary_photo_key'] ?? '');
        if ($key === '') {
            return ['classification' => self::CLASSIFICATION_HEALTHY, 'reasons' => ['no_primary_photo']];
        }

        $expectedHash = $canonical['primary_photo_sha256'] ?? null;
        if (! is_string($expectedHash) || $expectedHash === '') {
            // No known-good hash recorded (pre-monitor photo without backfill):
            // unverifiable, never a violation.
            return ['classification' => self::CLASSIFICATION_SCAN_ERROR, 'reasons' => ['photo_hash_missing']];
        }

        $diskName = filled($canonical['primary_photo_disk'] ?? null)
            ? (string) $canonical['primary_photo_disk']
            : 'public';

        try {
            $disk = Storage::disk($diskName);

            // Cheap check: existence + size (HEAD-equivalent).
            $size = $disk->size($key);
            if ($size === false) {
                return ['classification' => self::CLASSIFICATION_SCAN_ERROR, 'reasons' => ['photo_object_unreachable']];
            }

            $sizeMismatch = $canonical['primary_photo_size_bytes'] !== null
                && (int) $size !== (int) $canonical['primary_photo_size_bytes'];

            if (! $deepVerify && ! $sizeMismatch) {
                return ['classification' => self::CLASSIFICATION_HEALTHY, 'reasons' => ['metadata_check_passed']];
            }

            // Confirmation: streamed SHA-256 of the actual stored bytes.
            $actual = $this->streamedSha256($disk, $key);
            if ($actual === null) {
                return ['classification' => self::CLASSIFICATION_SCAN_ERROR, 'reasons' => ['photo_read_failed']];
            }

            if ($actual !== $expectedHash) {
                return ['classification' => self::CLASSIFICATION_MISMATCH, 'reasons' => [
                    'photo_sha256_mismatch:expected='.mb_substr($expectedHash, 0, 12).';actual='.mb_substr($actual, 0, 12),
                ]];
            }

            $reason = $sizeMismatch ? 'photo_size_column_stale_but_bytes_verified' : 'sha256_verified';
            Log::debug('profile-integrity: photo verified', ['key_tail' => mb_substr($key, -16), 'result' => $reason]);

            return ['classification' => self::CLASSIFICATION_HEALTHY, 'reasons' => [$reason]];
        } catch (Throwable) {
            return ['classification' => self::CLASSIFICATION_SCAN_ERROR, 'reasons' => ['photo_check_infra_error']];
        }
    }

    /**
     * @return string|null SHA-256 or null when the object cannot be read
     */
    private function streamedSha256($disk, string $key): ?string
    {
        try {
            $context = hash_init('sha256');
            $stream = $disk->readStream($key);
            if (! is_resource($stream)) {
                return null;
            }
            while (! feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    fclose($stream);

                    return null;
                }
                hash_update($context, $chunk);
            }
            fclose($stream);

            return hash_final($context);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Which monitored sections differ between snapshot and current state.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $current
     * @return array<string, array{expected: mixed, current: mixed}>
     */
    private function sectionDiffs(array $expected, array $current): array
    {
        $sections = [
            'identity' => ['slug', 'display_name', 'profession'],
            'editorial' => [
                'english_editorial_content_id', 'english_content_hash',
                'malayalam_editorial_content_id', 'malayalam_content_hash',
            ],
            'photos' => [
                'primary_photo_media_id', 'primary_photo_disk', 'primary_photo_key',
                'primary_photo_sha256', 'primary_photo_size_bytes', 'photos',
            ],
            'public_offices' => ['public_offices'],
            'video_links' => ['video_links'],
        ];

        $diffs = [];
        foreach ($sections as $section => $fields) {
            $expectedSection = $this->pick($expected, $fields);
            $currentSection = $this->pick($current, $fields);
            if ($this->stable($expectedSection) !== $this->stable($currentSection)) {
                $diffs[$section] = ['expected' => $expectedSection, 'current' => $currentSection];
            }
        }

        return $diffs;
    }

    /**
     * Does a NEWER audit event exist that actually EXPLAINS this section's
     * transition? "An event exists" is not sufficient.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $current
     * @return list<int>|null matching StaffActionLog ids, or null when unexplained
     */
    private function sectionAuthorized(
        Profile $profile,
        ProfileIntegritySnapshot $snapshot,
        string $section,
        array $expected,
        array $current,
    ): ?array {
        $actions = self::AUTHORIZED_ACTIONS[$section] ?? [];
        if ($actions === []) {
            return null; // No application path can authorize this section.
        }

        $since = $snapshot->updated_at ?? $snapshot->created_at;

        $events = StaffActionLog::query()
            ->whereIn('action', $actions)
            ->where('created_at', '>', $since)
            ->orderBy('id')
            ->get();

        // Editorial diffs are multi-dimensional (English/Malayalam id + hash).
        // Every changed dimension must be covered; one event must not clear
        // unrelated dimensions.
        if ($section === 'editorial') {
            return $this->editorialSectionAuthorized($profile, $events, $expected, $current);
        }

        foreach ($events as $event) {
            if ($this->eventExplainsSection($event, $section, $profile, $expected, $current)) {
                return [$event->id];
            }
        }

        return null;
    }

    /**
     * Require evidence for every changed English/Malayalam id or hash.
     * Snapshot refresh (watchdog_reconciled) only proceeds when this returns ids.
     *
     * @param  \Illuminate\Support\Collection<int, StaffActionLog>  $events
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $current
     * @return list<int>|null
     */
    private function editorialSectionAuthorized(
        Profile $profile,
        $events,
        array $expected,
        array $current,
    ): ?array {
        $remaining = array_fill_keys(
            $this->editorialChangedDimensions($expected, $current),
            true,
        );
        if ($remaining === []) {
            return null;
        }

        $eventIds = [];
        foreach ($events as $event) {
            $covered = $this->editorialEventCoversDimensions(
                $event,
                $profile,
                $expected,
                $current,
                array_keys($remaining),
            );
            foreach ($covered as $dimension) {
                if (isset($remaining[$dimension])) {
                    unset($remaining[$dimension]);
                    $eventIds[] = $event->id;
                }
            }
            if ($remaining === []) {
                return array_values(array_unique($eventIds));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    private function editorialChangedDimensions(array $expected, array $current): array
    {
        $changed = [];
        if ((int) ($expected['english_editorial_content_id'] ?? 0) !== (int) ($current['english_editorial_content_id'] ?? 0)) {
            $changed[] = 'english_id';
        }
        if (($expected['english_content_hash'] ?? null) !== ($current['english_content_hash'] ?? null)) {
            $changed[] = 'english_hash';
        }
        if ((int) ($expected['malayalam_editorial_content_id'] ?? 0) !== (int) ($current['malayalam_editorial_content_id'] ?? 0)) {
            $changed[] = 'malayalam_id';
        }
        if (($expected['malayalam_content_hash'] ?? null) !== ($current['malayalam_content_hash'] ?? null)) {
            $changed[] = 'malayalam_hash';
        }

        return $changed;
    }

    private function eventExplainsSection(
        StaffActionLog $event,
        string $section,
        Profile $profile,
        array $expected,
        array $current,
    ): bool {
        switch ($section) {
            case 'identity':
                // Only slug changes have an authorized path; name/profession
                // have none, so a slug event must explain the exact transition
                // on THIS profile.
                if ($event->subject_type !== $profile->getMorphClass()
                    || (int) $event->subject_id !== (int) $profile->id) {
                    return false;
                }
                $before = (string) ($event->before['slug'] ?? '');
                $after = (string) ($event->after['slug'] ?? '');
                if ($after === '') {
                    return false;
                }
                $slugChangedOnly = $this->pick($expected, ['display_name', 'profession']) === $this->pick($current, ['display_name', 'profession']);

                return $slugChangedOnly
                    && strtolower($before) === (string) $expected['slug']
                    && strtolower($after) === (string) $current['slug'];

            case 'editorial':
                // Handled by editorialSectionAuthorized() — never authorize the
                // whole section from a single coarse boolean.
                return false;

            case 'photos':
                $mediaMorph = (new MediaItem)->getMorphClass();
                if ($event->subject_type !== $mediaMorph) {
                    return false;
                }
                $media = MediaItem::query()->find((int) $event->subject_id);
                if (! $media instanceof MediaItem
                    || $media->mediable_type !== $profile->getMorphClass()
                    || (int) $media->mediable_id !== (int) $profile->id) {
                    return false;
                }
                $currentIds = array_map(fn (array $p): int => $p['media_id'], $current['photos'] ?? []);
                $expectedIds = array_map(fn (array $p): int => $p['media_id'], $expected['photos'] ?? []);

                return in_array((int) $event->subject_id, array_merge($currentIds, $expectedIds), true);

            case 'video_links':
                $linkMorph = (new \App\Models\ProfileExternalLink)->getMorphClass();
                if ($event->subject_type !== $linkMorph) {
                    return false;
                }
                $link = \App\Models\ProfileExternalLink::query()->find((int) $event->subject_id);
                if (! $link instanceof \App\Models\ProfileExternalLink
                    || (int) $link->profile_id !== (int) $profile->id) {
                    return false;
                }

                // The event must concern a link that is part of the observed
                // public set, or that was removed from the prior set.
                $currentUrls = array_map(
                    fn (array $row): string => (string) ($row['url'] ?? ''),
                    $current['video_links'] ?? [],
                );
                $expectedUrls = array_map(
                    fn (array $row): string => (string) ($row['url'] ?? ''),
                    $expected['video_links'] ?? [],
                );
                $eventUrl = (string) ($event->after['url'] ?? $link->url ?? '');
                if ($eventUrl !== '' && (in_array($eventUrl, $currentUrls, true) || in_array($eventUrl, $expectedUrls, true))) {
                    return true;
                }

                // Approval / rejection events often store host only; the link
                // row belonging to this profile and appearing in either set
                // (by matching any current/expected URL for this link id via
                // the live row URL) is sufficient when the URL moved in/out.
                $linkUrl = (string) ($link->url ?? '');

                return $linkUrl !== ''
                    && (in_array($linkUrl, $currentUrls, true) || in_array($linkUrl, $expectedUrls, true));
        }

        return false;
    }

    /**
     * Dimensions among $needed that this single audit event can cover.
     * Publication events move pointers; narrative events explain same-id rewrites.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $current
     * @param  list<string>  $needed
     * @return list<string>
     */
    private function editorialEventCoversDimensions(
        StaffActionLog $event,
        Profile $profile,
        array $expected,
        array $current,
        array $needed,
    ): array {
        if ($needed === []) {
            return [];
        }

        $applicationMorph = (new Application)->getMorphClass();
        $editorialMorph = (new EditorialContent)->getMorphClass();

        $currentEnglishId = (int) ($current['english_editorial_content_id'] ?? 0);
        $currentMalayalamId = (int) ($current['malayalam_editorial_content_id'] ?? 0);
        $expectedEnglishId = (int) ($expected['english_editorial_content_id'] ?? 0);
        $expectedMalayalamId = (int) ($expected['malayalam_editorial_content_id'] ?? 0);
        $currentEnglishHash = $current['english_content_hash'] ?? null;
        $currentMalayalamHash = $current['malayalam_content_hash'] ?? null;
        $englishIdUnchanged = $expectedEnglishId > 0 && $expectedEnglishId === $currentEnglishId;
        $malayalamIdUnchanged = $expectedMalayalamId > 0 && $expectedMalayalamId === $currentMalayalamId;
        $englishHashChanged = ($expected['english_content_hash'] ?? null) !== $currentEnglishHash;
        $malayalamHashChanged = ($expected['malayalam_content_hash'] ?? null) !== $currentMalayalamHash;

        if (in_array($event->action, ['application.published', 'application.published.replacement', 'application.malayalam_published'], true)) {
            if ($event->subject_type !== $applicationMorph) {
                return [];
            }
            $application = Application::query()->find((int) $event->subject_id);
            if (! $application instanceof Application
                || (int) $application->profile_id !== (int) $profile->id) {
                return [];
            }

            $afterEnglish = (int) ($event->after['published_english_editorial_content_id'] ?? 0);
            $afterMalayalam = (int) ($event->after['published_malayalam_editorial_content_id'] ?? 0);
            $covered = [];

            if ($event->action === 'application.malayalam_published') {
                // Malayalam publish never authorises English pointer or narrative changes.
                if ($afterMalayalam <= 0
                    || $afterMalayalam !== $currentMalayalamId
                    || ! $this->publishedEditorialHashMatches($currentMalayalamId, $currentMalayalamHash)) {
                    return [];
                }
                // Same Malayalam id with a changed hash is an in-place rewrite —
                // pointer publication cannot explain it.
                if ($malayalamIdUnchanged && $malayalamHashChanged) {
                    return [];
                }
                if (in_array('malayalam_id', $needed, true) && ! $malayalamIdUnchanged) {
                    $covered[] = 'malayalam_id';
                }
                if (in_array('malayalam_hash', $needed, true) && ! ($malayalamIdUnchanged && $malayalamHashChanged)) {
                    $covered[] = 'malayalam_hash';
                }

                return $covered;
            }

            // Living publish / replacement: English pointer + hash for the new
            // id. Never covers same-id English/Malayalam narrative rewrites.
            if ($currentEnglishId <= 0
                || $afterEnglish !== $currentEnglishId
                || ! $this->publishedEditorialHashMatches($currentEnglishId, $currentEnglishHash)) {
                return [];
            }
            if ($currentMalayalamId > 0 && $afterMalayalam > 0 && $afterMalayalam !== $currentMalayalamId) {
                return [];
            }
            if ($currentMalayalamId > 0
                && ! $this->publishedEditorialHashMatches($currentMalayalamId, $currentMalayalamHash)) {
                return [];
            }

            if (in_array('english_id', $needed, true) && ! $englishIdUnchanged) {
                $covered[] = 'english_id';
            }
            if (in_array('english_hash', $needed, true) && ! ($englishIdUnchanged && $englishHashChanged)) {
                $covered[] = 'english_hash';
            }
            if (in_array('malayalam_id', $needed, true)
                && ! $malayalamIdUnchanged
                && $afterMalayalam === $currentMalayalamId
                && $currentMalayalamId > 0) {
                $covered[] = 'malayalam_id';
            }
            if (in_array('malayalam_hash', $needed, true)
                && ! ($malayalamIdUnchanged && $malayalamHashChanged)
                && $currentMalayalamId > 0
                && ($afterMalayalam === 0 || $afterMalayalam === $currentMalayalamId)) {
                $covered[] = 'malayalam_hash';
            }

            return $covered;
        }

        // Narrative / version audits: same-id hash changes only. Never pointer swaps.
        if ($event->subject_type !== $editorialMorph) {
            return [];
        }

        $content = EditorialContent::query()->find((int) $event->subject_id);
        if (! $content instanceof EditorialContent
            || (int) $content->profile_id !== (int) $profile->id) {
            return [];
        }

        $contentId = (int) $content->id;
        $isEnglish = $contentId === $currentEnglishId && $currentEnglishId > 0;
        $isMalayalam = $contentId === $currentMalayalamId && $currentMalayalamId > 0;
        if (! $isEnglish && ! $isMalayalam) {
            return [];
        }

        $liveHash = $this->contentHash($content);
        $presentationHash = $isEnglish ? $currentEnglishHash : $currentMalayalamHash;
        if ($liveHash !== $presentationHash) {
            return [];
        }

        if (! $this->editorialNarrativePayloadMatchesLive($event, $content, (string) $presentationHash)) {
            return [];
        }

        $covered = [];
        if ($isEnglish
            && in_array('english_hash', $needed, true)
            && $englishIdUnchanged
            && $englishHashChanged
            && ! in_array('english_id', $needed, true)) {
            // Same-id English rewrite only. Pointer swaps need publication evidence.
            $covered[] = 'english_hash';
        }
        if ($isMalayalam
            && in_array('malayalam_hash', $needed, true)
            && $malayalamIdUnchanged
            && $malayalamHashChanged
            && ! in_array('malayalam_id', $needed, true)) {
            $covered[] = 'malayalam_hash';
        }

        return $covered;
    }

    /**
     * Same-id narrative rewrite evidence: body or content_hash required; no
     * live-row fallback for missing body.
     */
    private function editorialNarrativePayloadMatchesLive(
        StaffActionLog $event,
        EditorialContent $content,
        string $presentationHash,
    ): bool {
        $after = is_array($event->after) ? $event->after : [];
        if (! array_key_exists('body', $after) && ! array_key_exists('content_hash', $after)) {
            return false;
        }

        if (isset($after['content_hash']) && is_string($after['content_hash'])) {
            return hash_equals($presentationHash, $after['content_hash']);
        }

        $payloadHash = hash('sha256', $this->stable([
            'title' => (string) ($after['title'] ?? $content->title),
            'summary' => (string) ($after['summary'] ?? $content->summary),
            'body' => (string) $after['body'],
        ]));

        foreach (['title', 'summary', 'body'] as $field) {
            if (array_key_exists($field, $after)
                && (string) $after[$field] !== (string) $content->{$field}) {
                return false;
            }
        }

        return hash_equals($presentationHash, $payloadHash);
    }

    private function publishedEditorialHashMatches(int $editorialContentId, mixed $expectedHash): bool
    {
        if ($editorialContentId <= 0 || ! is_string($expectedHash) || $expectedHash === '') {
            return $editorialContentId <= 0 && ($expectedHash === null || $expectedHash === '');
        }

        $content = EditorialContent::query()->find($editorialContentId);
        if (! $content instanceof EditorialContent) {
            return false;
        }

        return hash_equals($expectedHash, $this->contentHash($content));
    }

    /**
     * Append-only incident creation with open-incident dedupe so an unchanged
     * condition is not reported unboundedly across runs.
     *
     * @param  array<string, mixed>|null  $correlatedEventIds
     */
    private function recordIncident(
        Profile $profile,
        string $runId,
        string $type,
        string $reason,
        array $expectedState,
        array $observedState,
        ?array $correlatedEventIds = null,
    ): IntegrityIncident {
        $fingerprint = hash(
            'sha256',
            $profile->id.'|'.$type.'|'.$this->stable($expectedState).'|'.$this->stable($observedState),
        );

        $existing = IntegrityIncident::query()
            ->where('profile_id', $profile->id)
            ->where('type', $type)
            ->where('detection_fingerprint', $fingerprint)
            ->where('status', IntegrityIncident::STATUS_OPEN)
            ->first();

        if ($existing instanceof IntegrityIncident) {
            return $existing;
        }

        try {
            return IntegrityIncident::query()->create([
                'profile_id' => $profile->id,
                'run_id' => $runId,
                'type' => $type,
                'mode' => (string) config(
                    'jannayaks.security.integrity.mode',
                    config('jannayaks.integrity.mode', 'report'),
                ),
                'detection_reason' => $reason,
                'expected_state' => $expectedState,
                'observed_state' => $observedState,
                'detection_fingerprint' => $fingerprint,
                'correlated_event_ids' => $correlatedEventIds,
                'detected_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Concurrent writer won the partial unique index.
            $raced = IntegrityIncident::query()
                ->where('profile_id', $profile->id)
                ->where('type', $type)
                ->where('detection_fingerprint', $fingerprint)
                ->where('status', IntegrityIncident::STATUS_OPEN)
                ->first();

            if ($raced instanceof IntegrityIncident) {
                return $raced;
            }

            throw $e;
        }
    }

    private function incidentTypeForSection(string $section): string
    {
        return match ($section) {
            'photos' => IntegrityIncident::TYPE_PHOTOGRAPH_REFERENCE,
            'editorial' => IntegrityIncident::TYPE_EDITORIAL,
            'identity' => IntegrityIncident::TYPE_IDENTITY,
            'public_offices' => IntegrityIncident::TYPE_OFFICES,
            'video_links' => IntegrityIncident::TYPE_VIDEO_LINKS,
            'missing_snapshot' => IntegrityIncident::TYPE_MISSING_SNAPSHOT,
            default => 'unknown',
        };
    }

    private function contentHash(EditorialContent $content): string
    {
        return hash('sha256', $this->stable([
            'title' => (string) $content->title,
            'summary' => (string) $content->summary,
            'body' => (string) $content->body,
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function canonicalOffices(Profile $profile): array
    {
        return $profile->publicOffices
            ->map(fn ($office): array => [
                'office_name' => (string) $office->office_name,
                'where_location' => (string) ($office->where_location ?? ''),
                'term_summary' => (string) ($office->term_summary ?? ''),
            ])
            ->sortBy(fn (array $row): string => implode('|', $row), SORT_REGULAR)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function canonicalVideoLinks(Profile $profile): array
    {
        return $this->presentation->publicVideoLinks($profile)
            ->map(fn ($link): array => [
                'label' => (string) ($link->label ?? ''),
                'url' => (string) ($link->url ?? ''),
            ])
            ->sortBy(fn (array $row): string => implode('|', $row), SORT_REGULAR)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function primarySection(array $payload): array
    {
        return $this->pick($payload, [
            'primary_photo_media_id', 'primary_photo_disk', 'primary_photo_key',
            'primary_photo_sha256', 'primary_photo_size_bytes', 'photos',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function pick(array $payload, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field] = $payload[$field] ?? null;
        }

        return $out;
    }

    /**
     * Deterministic serialization for comparison and fingerprinting.
     * Keys are sorted recursively: Postgres jsonb round-trips objects with
     * normalized key order, so insertion order must never matter.
     *
     * @param  mixed  $value
     */
    private function stable($value): string
    {
        return json_encode(
            $this->sortKeysRecursively($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    private function sortKeysRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sorted = [];
        foreach ($value as $k => $v) {
            $sorted[$k] = $this->sortKeysRecursively($v);
        }
        ksort($sorted);

        return $sorted;
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function payloadHash(array $canonical): string
    {
        return hash('sha256', $this->stable($canonical));
    }
}

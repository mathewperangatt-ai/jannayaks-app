<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExternalVideoLinkService
{
    public function __construct(
        private ExternalUrlValidator $urls,
        private StaffAuditLogger $audit,
    ) {}

    public function tierAllowsVideo(Profile $profile): bool
    {
        $profile->loadMissing('application');
        $application = $profile->application;
        if (! $application instanceof Application) {
            return false;
        }

        return (bool) config(
            'jannayaks.tier_pricing.packages.'.$application->package_tier.'.includes_video_link',
            false,
        );
    }

    /**
     * Submit a new video link (pre-publication) or a change request (post-publication).
     *
     * @throws ValidationException
     */
    public function submit(Profile $profile, User $actor, string $url, ?string $label = null): ProfileExternalLink
    {
        $this->assertMemberOwner($profile, $actor);

        $profile->loadMissing('application');
        $application = $profile->application;
        if ($application instanceof Application
            && $application->memberDirectEditsLocked()
            && ! $profile->isPubliclyListed()) {
            // After approval but before publication: no direct member video mutations.
            throw ValidationException::withMessages([
                'url' => 'This profile has been approved. Video link changes must be handled by Jannayaks editorial staff.',
            ]);
        }

        if (! $this->tierAllowsVideo($profile)) {
            throw ValidationException::withMessages([
                'url' => 'External video links are not included with this package.',
            ]);
        }

        try {
            $normalized = $this->urls->validateHttpsUrl($url);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'url' => $e->getMessage(),
            ]);
        }

        $max = (int) config('jannayaks.media.video_links.max_per_profile', 10);
        $existingCount = $profile->externalLinks()->count();
        if ($existingCount >= $max) {
            throw ValidationException::withMessages([
                'url' => 'Too many video link records for this profile.',
            ]);
        }

        $active = $profile->externalLinks()
            ->where('link_type', ProfileExternalLink::TYPE_VIDEO)
            ->where('is_publicly_active', true)
            ->where('status', ProfileExternalLink::STATUS_APPROVED)
            ->first();

        $isPublished = $profile->isPubliclyListed();

        // Post-publication: never silently replace the live link — create a change request.
        if ($isPublished && $active) {
            return $this->createChangeRequest($profile, $actor, $normalized, $label, $active);
        }

        // Pre-publication (or published with no active link): pending review; not live until approved.
        // If the profile is not yet published, approval can activate immediately for editorial readiness
        // without public exposure (publication gate still applies).
        $link = ProfileExternalLink::query()->create([
            'profile_id' => $profile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => $this->sanitizeLabel($label),
            'url' => $normalized,
            'status' => ProfileExternalLink::STATUS_PENDING_REVIEW,
            'is_publicly_active' => false,
            'submitted_by_user_id' => $actor->id,
            'replaces_link_id' => null,
        ]);

        $this->audit->log(
            action: 'media.external_video_submitted',
            subject: $link,
            before: null,
            after: [
                'profile_id' => $profile->id,
                'url_host' => parse_url($normalized, PHP_URL_HOST),
                'status' => $link->status,
            ],
            actor: $actor,
        );

        return $link;
    }

    /**
     * @throws ValidationException
     */
    public function approve(ProfileExternalLink $link, User $reviewer, ?string $note = null): ProfileExternalLink
    {
        if (! $reviewer->canManageEditorial()) {
            throw ValidationException::withMessages([
                'link' => 'You are not allowed to approve external video links.',
            ]);
        }

        if ($link->status !== ProfileExternalLink::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'link' => 'Only pending video links can be approved.',
            ]);
        }

        return DB::transaction(function () use ($link, $reviewer, $note): ProfileExternalLink {
            $link = ProfileExternalLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();
            $before = [
                'status' => $link->status,
                'is_publicly_active' => $link->is_publicly_active,
            ];

            if ($link->replaces_link_id) {
                $previous = ProfileExternalLink::query()
                    ->whereKey($link->replaces_link_id)
                    ->lockForUpdate()
                    ->first();

                if ($previous) {
                    $previous->forceFill([
                        'status' => ProfileExternalLink::STATUS_REPLACED,
                        'is_publicly_active' => false,
                    ])->save();
                }
            } else {
                // Deactivate any other active video for this profile.
                ProfileExternalLink::query()
                    ->where('profile_id', $link->profile_id)
                    ->where('link_type', ProfileExternalLink::TYPE_VIDEO)
                    ->where('is_publicly_active', true)
                    ->whereKeyNot($link->id)
                    ->update([
                        'is_publicly_active' => false,
                        'status' => ProfileExternalLink::STATUS_REPLACED,
                    ]);
            }

            $link->forceFill([
                'status' => ProfileExternalLink::STATUS_APPROVED,
                'is_publicly_active' => true,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $this->sanitizeNote($note),
            ])->save();

            $this->audit->log(
                action: 'media.external_video_approved',
                subject: $link,
                before: $before,
                after: [
                    'status' => $link->status,
                    'is_publicly_active' => true,
                    'replaces_link_id' => $link->replaces_link_id,
                ],
                actor: $reviewer,
            );

            app(ProfileIntegrityService::class)->refreshSnapshot(
                $link->profile,
                'media.external_video_approved',
            );

            return $link->fresh() ?? $link;
        });
    }

    /**
     * @throws ValidationException
     */
    public function reject(ProfileExternalLink $link, User $reviewer, ?string $note = null): ProfileExternalLink
    {
        if (! $reviewer->canManageEditorial()) {
            throw ValidationException::withMessages([
                'link' => 'You are not allowed to reject external video links.',
            ]);
        }

        if ($link->status !== ProfileExternalLink::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'link' => 'Only pending video links can be rejected.',
            ]);
        }

        $before = [
            'status' => $link->status,
            'is_publicly_active' => $link->is_publicly_active,
        ];

        $link->forceFill([
            'status' => ProfileExternalLink::STATUS_REJECTED,
            'is_publicly_active' => false,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $this->sanitizeNote($note),
        ])->save();

        // Previous public link (if any) remains active — no change on rejection.
        $this->audit->log(
            action: 'media.external_video_rejected',
            subject: $link,
            before: $before,
            after: [
                'status' => $link->status,
                'is_publicly_active' => false,
            ],
            actor: $reviewer,
        );

        return $link->fresh() ?? $link;
    }

    /**
     * @return Collection<int, ProfileExternalLink>
     */
    public function publiclyActiveFor(Profile $profile)
    {
        return $profile->externalLinks
            ->filter(fn (ProfileExternalLink $link): bool => $link->isActiveVideo())
            ->values();
    }

    private function createChangeRequest(
        Profile $profile,
        User $actor,
        string $normalized,
        ?string $label,
        ProfileExternalLink $active,
    ): ProfileExternalLink {
        $pending = ProfileExternalLink::query()->create([
            'profile_id' => $profile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => $this->sanitizeLabel($label) ?? $active->label,
            'url' => $normalized,
            'status' => ProfileExternalLink::STATUS_PENDING_REVIEW,
            'is_publicly_active' => false,
            'submitted_by_user_id' => $actor->id,
            'replaces_link_id' => $active->id,
        ]);

        $this->audit->log(
            action: 'media.external_video_change_requested',
            subject: $pending,
            before: [
                'active_link_id' => $active->id,
                'active_url_host' => parse_url((string) $active->url, PHP_URL_HOST),
            ],
            after: [
                'pending_link_id' => $pending->id,
                'pending_url_host' => parse_url($normalized, PHP_URL_HOST),
                'status' => $pending->status,
            ],
            actor: $actor,
        );

        return $pending;
    }

    private function assertMemberOwner(Profile $profile, User $actor): void
    {
        if ((int) $profile->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'url' => 'You are not allowed to manage video links for this profile.',
            ]);
        }
    }

    private function sanitizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $clean = trim(strip_tags($label));

        return $clean === '' ? null : Str::limit($clean, 255, '');
    }

    private function sanitizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $clean = trim(strip_tags($note));

        return $clean === '' ? null : Str::limit($clean, 2000, '');
    }
}

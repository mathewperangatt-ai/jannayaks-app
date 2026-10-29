<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProfileAdministrationService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private ProfileUrlService $profileUrls,
        private ProfileIntegrityService $integrity,
    ) {}

    /**
     * Change the account email recorded against an existing application.
     * Ownership of the profile is not transferred.
     */
    public function changeApplicantEmail(Application $application, User $actor, string $email, string $reason): Application
    {
        $this->assertAdmin($actor, $reason);
        $normalized = Application::normalizeApplicantEmail($email);
        if ($normalized === null || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        return DB::transaction(function () use ($application, $actor, $normalized, $reason): Application {
            $this->lockEmail($normalized);
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

            $emailTaken = User::query()
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->whereKeyNot($user->id)
                ->exists();
            if ($emailTaken || Application::emailIsAssociated($normalized, (int) $locked->id, (int) $user->id)) {
                throw new InvalidArgumentException('That email address is already used by another account or application.');
            }

            $before = [
                'user_id' => $user->id,
                'email' => $user->email,
                'applicant_email_normalized' => $locked->applicant_email_normalized,
            ];
            try {
                $user->forceFill(['email' => $normalized])->save();
                $locked->forceFill(['applicant_email_normalized' => $normalized])->save();
            } catch (QueryException $exception) {
                if ((string) ($exception->errorInfo[0] ?? '') !== '23505') {
                    throw $exception;
                }

                throw new InvalidArgumentException('That email address is already used by another account or application.');
            }

            $this->audit->log(
                action: 'application.email_changed',
                subject: $locked,
                before: $before,
                after: [
                    'user_id' => $user->id,
                    'email' => $normalized,
                    'applicant_email_normalized' => $normalized,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Move an existing application and its profile to another account.
     * No second application or profile is created.
     */
    public function transferToAccount(Application $application, User $actor, User $target, string $reason): Application
    {
        $this->assertAdmin($actor, $reason);
        if ((int) $target->id === (int) $application->user_id) {
            throw new InvalidArgumentException('The profile is already on that account.');
        }

        $targetEmail = Application::normalizeApplicantEmail($target->email);
        if ($targetEmail === null) {
            throw new InvalidArgumentException('The destination account has no email address.');
        }

        return DB::transaction(function () use ($application, $actor, $target, $targetEmail, $reason): Application {
            $this->lockEmail($targetEmail);
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $targetLocked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            $targetHasProfile = Profile::query()->where('user_id', $targetLocked->id)->exists();
            if ($targetHasProfile) {
                throw new InvalidArgumentException('The destination account already has a profile.');
            }
            if (Application::emailIsAssociated($targetEmail, (int) $locked->id, (int) $targetLocked->id)
                || Application::query()->where('user_id', $targetLocked->id)->exists()) {
                throw new InvalidArgumentException('The destination account already has an application or profile.');
            }

            $profile = $locked->profile_id
                ? Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->first()
                : null;
            $before = [
                'user_id' => $locked->user_id,
                'applicant_email_normalized' => $locked->applicant_email_normalized,
                'profile_id' => $locked->profile_id,
                'profile_user_id' => $profile?->user_id,
            ];

            try {
                $locked->forceFill([
                    'user_id' => $targetLocked->id,
                    'applicant_email_normalized' => $targetEmail,
                ])->save();
                if ($profile instanceof Profile) {
                    $profile->forceFill(['user_id' => $targetLocked->id])->save();
                }
            } catch (QueryException $exception) {
                if ((string) ($exception->errorInfo[0] ?? '') !== '23505') {
                    throw $exception;
                }

                throw new InvalidArgumentException('The destination account already has an application or profile.');
            }

            $this->audit->log(
                action: 'application.account_transferred',
                subject: $locked,
                before: $before,
                after: [
                    'user_id' => $targetLocked->id,
                    'applicant_email_normalized' => $targetEmail,
                    'profile_id' => $locked->profile_id,
                    'profile_user_id' => $targetLocked->id,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    public function changeProfileUrl(Application $application, User $actor, string $slug, string $reason): Profile
    {
        $this->assertAdmin($actor, $reason);
        $profile = $application->profile;
        if (! $profile instanceof Profile) {
            throw new InvalidArgumentException('This application has no profile whose URL can be changed.');
        }

        $before = (string) $profile->slug;
        $updated = $this->profileUrls->selectPersonalSlug(
            $profile,
            $actor,
            $slug,
            (string) $application->package_tier,
        );

        $this->audit->log(
            action: 'profile.url_changed',
            subject: $updated,
            before: ['slug' => $before],
            after: ['slug' => $updated->slug, 'reason' => $reason],
            actor: $actor,
        );

        return $updated;
    }

    /**
     * Unpublish a living profile after Jannayaks has received official notice.
     * This does not create a memorial, copy content, or change the profile URL.
     */
    public function unpublishLivingProfile(Application $application, User $actor, string $reason): Profile
    {
        $this->assertAdmin($actor, $reason);

        return DB::transaction(function () use ($application, $actor, $reason): Profile {
            $profile = $this->lockLivingProfile($application);

            return $this->hideLivingProfile(
                $profile,
                $actor,
                $reason,
                auditAction: 'profile.living_unpublished',
                hideKind: 'official_unpublish',
            );
        });
    }

    /**
     * Temporarily hide a living profile from public view while preserving all
     * content, media, editorial versions, and integrity evidence. Distinct from
     * official unpublish only in the audit trail (action + hide_kind).
     */
    public function temporarilyHideLivingProfile(Application $application, User $actor, string $reason): Profile
    {
        $this->assertAdmin($actor, $reason);

        return DB::transaction(function () use ($application, $actor, $reason): Profile {
            $profile = $this->lockLivingProfile($application);

            return $this->hideLivingProfile(
                $profile,
                $actor,
                $reason,
                auditAction: 'profile.temporarily_hidden',
                hideKind: 'temporary',
            );
        });
    }

    /**
     * Explicitly restore public visibility after an administrator review.
     * Never called automatically. Does not modify content, media, or editorial
     * pointers. Refuses erased, suspended, never-published, and membership-lapsed
     * profiles that lifecycle rules require to stay unpublished.
     *
     * Ordinary restore requires the current public payload to match the trusted
     * integrity snapshot with no open content-mismatch incidents. When that
     * check fails, a separate non-empty $integrityWaiverReason is required;
     * the waiver is audited and does not resolve incidents or bypass lifecycle
     * restrictions.
     */
    public function restoreLivingProfileVisibility(
        Application $application,
        User $actor,
        string $reason,
        ?string $integrityWaiverReason = null,
    ): Profile {
        $this->assertAdmin($actor, $reason);

        return DB::transaction(function () use ($application, $actor, $reason, $integrityWaiverReason): Profile {
            $profile = $this->lockLivingProfile($application);
            $this->assertEligibleForVisibilityRestore($profile);

            if ($this->profileUrls->isPubliclyVisible($profile)) {
                return $profile->fresh() ?? $profile;
            }

            $assessment = $this->integrity->assessPublicContentTrust($profile);
            $waiver = trim((string) $integrityWaiverReason);
            $waiverUsed = false;

            if ($assessment['requires_integrity_waiver']) {
                if ($waiver === '') {
                    throw new InvalidArgumentException($this->integrityRestoreRefusalMessage($assessment));
                }
                $waiverUsed = true;
            }

            $before = [
                'profile_id' => $profile->id,
                'status' => $profile->status,
                'slug' => $profile->slug,
                'published_at' => $profile->published_at?->toIso8601String(),
                'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
                'suspended_at' => $profile->suspended_at?->toIso8601String(),
            ];

            $profile->forceFill([
                'status' => 'published',
                'unpublished_at' => null,
            ])->save();

            $this->audit->log(
                action: 'profile.visibility_restored',
                subject: $profile,
                before: $before,
                after: [
                    'profile_id' => $profile->id,
                    'status' => $profile->status,
                    'slug' => $profile->slug,
                    'published_at' => $profile->published_at?->toIso8601String(),
                    'unpublished_at' => null,
                    'reason' => $reason,
                    'integrity_verification_outcome' => $assessment['outcome'],
                    'integrity_differing_sections' => $assessment['differing_sections'],
                    'integrity_blocking_incident_ids' => $assessment['blocking_incident_ids'],
                    'integrity_waiver_used' => $waiverUsed,
                    'integrity_waiver_reason' => $waiverUsed ? $waiver : null,
                ],
                actor: $actor,
            );

            return $profile->fresh() ?? $profile;
        });
    }

    /**
     * Whether an administrator may offer "Restore public visibility" for this profile.
     * Lifecycle eligibility only — content-trust failures still show the action so an
     * explicit integrity waiver can be supplied server-side.
     */
    public function canRestoreLivingProfileVisibility(Profile $profile): bool
    {
        try {
            $this->assertEligibleForVisibilityRestore($profile);
        } catch (InvalidArgumentException) {
            return false;
        }

        return ! $this->profileUrls->isPubliclyVisible($profile);
    }

    /**
     * @param  array{
     *     outcome: string,
     *     differing_sections: list<string>,
     *     blocking_incident_ids: list<int>,
     *     requires_integrity_waiver: bool
     * }  $assessment
     */
    private function integrityRestoreRefusalMessage(array $assessment): string
    {
        return match ($assessment['outcome']) {
            'missing_snapshot' => 'Ordinary restoration is refused because this profile has no trusted integrity snapshot. Provide a separate integrity-waiver reason only after manual review, or establish a baseline first.',
            'mismatch' => 'Ordinary restoration is refused because the current public content does not match the trusted integrity snapshot'
                .(
                    $assessment['differing_sections'] !== []
                        ? ' (changed: '.implode(', ', $assessment['differing_sections']).').'
                        : '.'
                )
                .' Provide a separate integrity-waiver reason to override after review.',
            default => 'Ordinary restoration is refused while open content-mismatch integrity incidents remain. Provide a separate integrity-waiver reason after review; incidents are not resolved by a waiver.',
        };
    }

    /**
     * Compact admin-facing visibility label derived from live gates + latest hide audit.
     *
     * @return array{public: bool, label: string, last_hide_kind: string|null, last_reason: string|null}
     */
    public function visibilityAdminSummary(Profile $profile): array
    {
        $public = $this->profileUrls->isPubliclyVisible($profile);
        if ($public) {
            return [
                'public' => true,
                'label' => 'Public',
                'last_hide_kind' => null,
                'last_reason' => null,
            ];
        }

        if ($profile->erasure_completed_at !== null) {
            return [
                'public' => false,
                'label' => 'Erased — not public',
                'last_hide_kind' => null,
                'last_reason' => null,
            ];
        }

        if ($profile->suspended_at !== null || $profile->status === 'suspended') {
            return [
                'public' => false,
                'label' => 'Suspended — not public',
                'last_hide_kind' => null,
                'last_reason' => null,
            ];
        }

        if ($profile->published_at === null) {
            return [
                'public' => false,
                'label' => 'Not published',
                'last_hide_kind' => null,
                'last_reason' => null,
            ];
        }

        $latest = StaffActionLog::query()
            ->where('subject_type', $profile->getMorphClass())
            ->where('subject_id', $profile->id)
            ->whereIn('action', ['profile.temporarily_hidden', 'profile.living_unpublished'])
            ->orderByDesc('id')
            ->first();

        $hideKind = is_array($latest?->after) ? ($latest->after['hide_kind'] ?? null) : null;
        $reason = is_string($latest?->recorded_reason) && $latest->recorded_reason !== ''
            ? $latest->recorded_reason
            : null;

        if (($latest?->action === 'profile.temporarily_hidden') || $hideKind === 'temporary') {
            return [
                'public' => false,
                'label' => 'Temporarily hidden',
                'last_hide_kind' => 'temporary',
                'last_reason' => $reason,
            ];
        }

        if (($latest?->action === 'profile.living_unpublished') || $hideKind === 'official_unpublish') {
            return [
                'public' => false,
                'label' => 'Unpublished (official)',
                'last_hide_kind' => 'official_unpublish',
                'last_reason' => $reason,
            ];
        }

        return [
            'public' => false,
            'label' => 'Not public',
            'last_hide_kind' => null,
            'last_reason' => $reason,
        ];
    }

    private function hideLivingProfile(
        Profile $profile,
        User $actor,
        string $reason,
        string $auditAction,
        string $hideKind,
    ): Profile {
        $before = [
            'profile_id' => $profile->id,
            'status' => $profile->status,
            'slug' => $profile->slug,
            'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
        ];

        // Already hidden via the shared inactive + unpublished_at shape: no
        // content change and no duplicate audit row.
        $alreadyHidden = $profile->status === 'inactive' && $profile->unpublished_at !== null;
        if ($alreadyHidden) {
            return $profile->fresh() ?? $profile;
        }

        $profile->forceFill([
            'status' => 'inactive',
            'unpublished_at' => $profile->unpublished_at ?? now(),
        ])->save();

        $this->audit->log(
            action: $auditAction,
            subject: $profile,
            before: $before,
            after: [
                'profile_id' => $profile->id,
                'status' => $profile->status,
                'slug' => $profile->slug,
                'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
                'hide_kind' => $hideKind,
                'reason' => $reason,
            ],
            actor: $actor,
        );

        return $profile->fresh() ?? $profile;
    }

    private function lockLivingProfile(Application $application): Profile
    {
        /** @var Application $locked */
        $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
        $profile = $locked->profile_id
            ? Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->first()
            : null;
        if (! $profile instanceof Profile) {
            throw new InvalidArgumentException('This application has no living profile.');
        }

        return $profile;
    }

    private function assertEligibleForVisibilityRestore(Profile $profile): void
    {
        if ($profile->published_at === null) {
            throw new InvalidArgumentException('Only a previously published profile can have public visibility restored.');
        }
        if ($profile->erasure_completed_at !== null) {
            throw new InvalidArgumentException('An erased profile cannot have public visibility restored.');
        }
        if ($profile->suspended_at !== null || $profile->status === 'suspended') {
            throw new InvalidArgumentException('A suspended profile cannot have public visibility restored.');
        }

        $membership = $profile->membership;
        if ($membership !== null) {
            $lifecycle = app(MembershipLifecycleService::class);
            if ($membership->status === 'lapsed' || $lifecycle->isPastGracePeriod($membership)) {
                throw new InvalidArgumentException(
                    'Membership lifecycle requires this profile to remain unpublished. Restore visibility only after a valid renewal reactivates membership.'
                );
            }
        }
    }

    private function assertAdmin(User $actor, string $reason): void
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only an active administrator can perform this action.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required.');
        }
    }

    private function lockEmail(string $email): void
    {
        DB::statement('SELECT pg_advisory_xact_lock(740261, hashtext(?))', [$email]);
    }
}

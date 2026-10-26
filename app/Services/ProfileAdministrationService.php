<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Models\SlugRedirect;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProfileAdministrationService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private ProfileUrlService $profileUrls,
        private InMemoriamUrlService $memorialUrls,
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
            if ($emailTaken || $this->emailOwnsAnotherOpenApplication($normalized, (int) $locked->id)) {
                throw new InvalidArgumentException('That email address is already used by another account or open application.');
            }

            $before = [
                'user_id' => $user->id,
                'email' => $user->email,
                'applicant_email_normalized' => $locked->applicant_email_normalized,
            ];
            $user->forceFill(['email' => $normalized])->save();
            $locked->forceFill(['applicant_email_normalized' => $normalized])->save();

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
            if ($this->emailOwnsAnotherOpenApplication($targetEmail, (int) $locked->id)
                || Application::query()
                    ->where('user_id', $targetLocked->id)
                    ->whereIn('status', Application::openLifecycleStatuses())
                    ->exists()) {
                throw new InvalidArgumentException('The destination account already has an open application.');
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

            $locked->forceFill([
                'user_id' => $targetLocked->id,
                'applicant_email_normalized' => $targetEmail,
            ])->save();
            if ($profile instanceof Profile) {
                $profile->forceFill(['user_id' => $targetLocked->id])->save();
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
     * Convert a living profile into a memorial draft using the memorial URL
     * rules. History, media, consent, and payments stay on the living records.
     * The memorial is not published by this action.
     */
    public function convertLivingProfileToMemorial(
        Application $application,
        User $actor,
        string $commissionerName,
        string $commissionerMobile,
        string $reason,
    ): InMemoriamProfile {
        $this->assertAdmin($actor, $reason);
        $commissionerName = trim($commissionerName);
        $commissionerMobile = trim($commissionerMobile);
        if ($commissionerName === '' || $commissionerMobile === '') {
            throw new InvalidArgumentException('The commissioner name and mobile number are required.');
        }

        return DB::transaction(function () use ($application, $actor, $commissionerName, $commissionerMobile, $reason): InMemoriamProfile {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $profile = $locked->profile_id
                ? Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->first()
                : null;
            if (! $profile instanceof Profile) {
                throw new InvalidArgumentException('This application has no living profile to convert.');
            }

            $existing = InMemoriamProfile::query()->where('living_profile_id', $profile->id)->first();
            if ($existing instanceof InMemoriamProfile) {
                return $existing;
            }

            $previousSlug = $profile->slug;
            $memorial = InMemoriamProfile::query()->create([
                'commissioner_user_id' => $actor->id,
                'living_profile_id' => $profile->id,
                'status' => InMemoriamProfile::STATUS_DRAFT,
                'commissioner_contact_name' => $commissionerName,
                'commissioner_contact_mobile' => $commissionerMobile,
                'deceased_full_name' => (string) ($profile->full_name ?: $locked->full_name),
                'deceased_display_name' => (string) ($profile->display_name ?: $profile->full_name ?: $locked->full_name),
                'verification_status' => InMemoriamProfile::VERIFICATION_UNVERIFIED,
                'profession' => (string) $profile->profession,
            ]);

            $slug = $this->memorialUrls->generateCanonicalSlug($memorial);
            $memorial = $this->memorialUrls->assignSlug($memorial, $slug, $actor);

            if (filled($previousSlug)) {
                SlugRedirect::query()->create([
                    'old_slug' => (string) $previousSlug,
                    'new_slug' => (string) $memorial->slug,
                    'redirectable_type' => $memorial->getMorphClass(),
                    'redirectable_id' => $memorial->id,
                ]);
            }

            $profile->forceFill([
                'slug' => null,
                'previous_slug' => $previousSlug,
                'status' => 'inactive',
                'unpublished_at' => $profile->unpublished_at ?? now(),
            ])->save();

            $this->audit->log(
                action: 'profile.converted_to_memorial',
                subject: $profile,
                before: [
                    'profile_id' => $profile->id,
                    'slug' => $previousSlug,
                    'status' => 'living',
                ],
                after: [
                    'profile_id' => $profile->id,
                    'memorial_id' => $memorial->id,
                    'memorial_slug' => $memorial->slug,
                    'application_id' => $locked->id,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return $memorial->fresh() ?? $memorial;
        });
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

    private function emailOwnsAnotherOpenApplication(string $email, int $ignoreApplicationId): bool
    {
        return Application::query()
            ->where('applicant_email_normalized', $email)
            ->whereIn('status', Application::openLifecycleStatuses())
            ->whereKeyNot($ignoreApplicationId)
            ->lockForUpdate()
            ->exists();
    }
}

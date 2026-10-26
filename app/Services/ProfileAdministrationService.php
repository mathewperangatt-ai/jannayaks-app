<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProfileAdministrationService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private ProfileUrlService $profileUrls,
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
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $profile = $locked->profile_id
                ? Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->first()
                : null;
            if (! $profile instanceof Profile) {
                throw new InvalidArgumentException('This application has no living profile to unpublish.');
            }

            $before = [
                'profile_id' => $profile->id,
                'status' => $profile->status,
                'slug' => $profile->slug,
                'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
            ];
            $alreadyUnpublished = $profile->status === 'inactive' && $profile->unpublished_at !== null;
            if (! $alreadyUnpublished) {
                $profile->forceFill([
                    'status' => 'inactive',
                    'unpublished_at' => $profile->unpublished_at ?? now(),
                ])->save();

                $this->audit->log(
                    action: 'profile.living_unpublished',
                    subject: $profile,
                    before: $before,
                    after: [
                        'profile_id' => $profile->id,
                        'status' => $profile->status,
                        'slug' => $profile->slug,
                        'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
                        'reason' => $reason,
                    ],
                    actor: $actor,
                );
            }

            return $profile->fresh() ?? $profile;
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
}

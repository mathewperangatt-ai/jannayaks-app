<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Models\ReservedSlug;
use App\Models\SlugRedirect;
use App\Models\SlugReservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProfileUrlService
{
    public const EMERGING_LENGTH = 6;

    public const PERSONAL_TIERS = ['accomplished', 'distinguished'];

    private const EMERGING_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const MAX_GENERATION_ATTEMPTS = 32;

    public function __construct(
        private PersonalSlugGenerator $personalSlugs,
        private StaffAuditLogger $auditLogger,
    ) {}

    /**
     * Ensure a published living profile has a canonical slug.
     * Emerging (and A/D before personal selection) receive a 6-character system URL.
     */
    public function assignInitialCanonicalSlug(Profile $profile, string $packageTier, ?User $actor = null): Profile
    {
        if ($packageTier === 'in_memoriam') {
            throw new InvalidArgumentException('In Memoriam profiles are outside living profile URL assignment.');
        }

        return DB::transaction(function () use ($profile, $actor, $packageTier) {
            /** @var Profile $locked */
            $locked = Profile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if (filled($locked->slug)) {
                $this->reserveSlug((string) $locked->slug, $locked);

                return $locked;
            }

            $slug = $this->generateUniqueEmergingSlug();
            $now = now();

            $locked->forceFill([
                'slug' => $slug,
                'slug_generated_at' => $now,
                'slug_changed_at' => $now,
            ])->save();

            // Authorized-change evidence for the public URL.
            $this->auditLogger->log(
                action: 'profile_url.slug_assigned',
                subject: $locked,
                before: ['slug' => null],
                after: ['slug' => $slug, 'package_tier' => $packageTier],
                actor: $actor,
            );

            $this->reserveSlug($slug, $locked);

            return $locked->fresh() ?? $locked;
        });
    }

    public function packageTierAllowsPersonalSlug(string $packageTier): bool
    {
        return in_array(strtolower($packageTier), self::PERSONAL_TIERS, true);
    }

    public function normalizePersonalSlug(string $raw): string
    {
        return strtolower(trim($raw));
    }

    public function isEmergingSystemSlug(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            return false;
        }

        return preg_match('/^[A-Z0-9]{'.self::EMERGING_LENGTH.'}$/', $slug) === 1;
    }

    public function hasPermanentPersonalSlug(Profile $profile): bool
    {
        return filled($profile->slug) && ! $this->isEmergingSystemSlug((string) $profile->slug);
    }

    /**
     * @return array{ok: bool, slug?: string, error?: string}
     */
    public function validatePersonalSlugCandidate(
        string $raw,
        ?int $ignoreProfileId = null,
        ?string $fullName = null,
        ?string $profession = null,
    ): array {
        $slug = $this->normalizePersonalSlug($raw);

        if ($slug === '') {
            return ['ok' => false, 'error' => 'A personal URL is required.'];
        }

        if (preg_match('/\s/', $raw) === 1) {
            return ['ok' => false, 'error' => 'Personal URLs cannot contain spaces.'];
        }

        if (str_contains($raw, '/') || str_contains($raw, '\\') || str_contains($raw, '..')) {
            return ['ok' => false, 'error' => 'Personal URLs cannot contain path separators.'];
        }

        if (str_contains($raw, '?') || str_contains($raw, '#') || str_contains($raw, '&') || str_contains($raw, '=')) {
            return ['ok' => false, 'error' => 'Personal URLs cannot contain query or fragment characters.'];
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $raw) === 1) {
            return ['ok' => false, 'error' => 'Personal URLs cannot contain control characters.'];
        }

        if (preg_match('/^[a-z0-9]+(?:[.\-][a-z0-9]+)*$/', $slug) !== 1) {
            return ['ok' => false, 'error' => 'Use lowercase letters, numbers, and single dots or hyphens only (e.g. arun.kumar).'];
        }

        if (strlen($slug) < 3 || strlen($slug) > 64) {
            return ['ok' => false, 'error' => 'Personal URLs must be between 3 and 64 characters.'];
        }

        if (preg_match('/^[0-9]+$/', $slug) === 1) {
            return ['ok' => false, 'error' => 'Personal URLs must be derived from the verified name.'];
        }

        if ($this->isReserved($slug)) {
            return ['ok' => false, 'error' => 'That URL is reserved by the system.'];
        }

        $name = $fullName;
        $job = $profession;
        if ($ignoreProfileId !== null && ($name === null || $name === '')) {
            $profile = Profile::query()->find($ignoreProfileId);
            $name = $profile?->full_name;
            $job = $job ?? $profile?->profession;
        }

        if (filled($name)) {
            if (! $this->personalSlugs->isNameDerived($slug, (string) $name)) {
                return ['ok' => false, 'error' => 'That URL must be recognisably derived from the verified name. Arbitrary usernames are not allowed.'];
            }

            $parsed = $this->personalSlugs->parsePersonalSlug($slug);
            if ($parsed !== null && ! $this->personalSlugs->titleIsConsistent($parsed['title'], $job)) {
                return ['ok' => false, 'error' => 'That professional title does not match the verified profile.'];
            }
        }

        if ($this->isSlugTaken($slug, $ignoreProfileId)) {
            return ['ok' => false, 'error' => 'That URL is not available.'];
        }

        return ['ok' => true, 'slug' => $slug];
    }

    /**
     * @return list<array{slug: string, available: bool}>
     */
    public function suggestPersonalSlugs(string $fullName, ?int $ignoreProfileId = null, int $limit = 8): array
    {
        $combinations = $this->personalSlugs->generateCombinations($fullName);
        $suggestions = [];
        $seen = [];

        foreach ($combinations as $candidate) {
            if (isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            $suggestions[] = [
                'slug' => $candidate,
                'available' => $this->validatePersonalSlugCandidate($candidate, $ignoreProfileId, $fullName)['ok'] === true,
            ];
            if (count($suggestions) >= $limit) {
                return $suggestions;
            }
        }

        foreach ($combinations as $base) {
            foreach ($this->personalSlugs->collisionSuffixes($ignoreProfileId) as $number) {
                $candidate = $this->personalSlugs->collisionCandidate($base, $number);
                if (isset($seen[$candidate])) {
                    continue;
                }
                if ($this->validatePersonalSlugCandidate($candidate, $ignoreProfileId, $fullName)['ok'] !== true) {
                    continue;
                }
                $seen[$candidate] = true;
                $suggestions[] = [
                    'slug' => $candidate,
                    'available' => true,
                ];
                if (count($suggestions) >= $limit) {
                    return $suggestions;
                }
                break;
            }
        }

        return $suggestions;
    }

    public function isReserved(string $slug): bool
    {
        $normalized = strtolower(trim($slug));
        $reserved = array_map('strtolower', (array) config('jannayaks.slug.reserved', []));

        if (in_array($normalized, $reserved, true)) {
            return true;
        }

        return ReservedSlug::query()
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->exists();
    }

    public function isSlugTaken(string $slug, ?int $ignoreProfileId = null): bool
    {
        $normalized = strtolower($slug);

        $profileQuery = Profile::query()->whereRaw('LOWER(slug) = ?', [$normalized]);
        if ($ignoreProfileId !== null) {
            $profileQuery->whereKeyNot($ignoreProfileId);
        }

        if ($profileQuery->exists()) {
            return true;
        }

        if (InMemoriamProfile::query()->whereRaw('LOWER(slug) = ?', [$normalized])->exists()) {
            return true;
        }

        if (SlugRedirect::query()->whereRaw('LOWER(old_slug) = ?', [$normalized])->exists()) {
            return true;
        }

        $reservationQuery = SlugReservation::query()->whereRaw('LOWER(slug) = ?', [$normalized]);
        if ($ignoreProfileId !== null) {
            $reservationQuery->where(function ($query) use ($ignoreProfileId): void {
                $query->whereNull('profile_id')
                    ->orWhere('profile_id', '!=', $ignoreProfileId);
            });
        }

        return $reservationQuery->exists();
    }

    /**
     * Member selects or changes a personal/name-based canonical URL (Accomplished/Distinguished only).
     */
    public function selectPersonalSlug(Profile $profile, User $actor, string $rawSlug, string $packageTier): Profile
    {
        $isStaff = $actor->canManageEditorial();

        if ((int) $profile->user_id !== (int) $actor->id && ! $isStaff) {
            throw new InvalidArgumentException('You may only change the URL for your own profile.');
        }

        if (! $this->packageTierAllowsPersonalSlug($packageTier)) {
            throw new InvalidArgumentException('Your membership tier uses a system-assigned profile URL and cannot choose a personal URL.');
        }

        if (! $isStaff) {
            $profile->loadMissing('application');
            $application = $profile->application;
            if ($application instanceof Application && $application->memberDirectEditsLocked()) {
                throw new InvalidArgumentException('Your profile URL is locked after approval. Contact Jannayaks if a correction is required.');
            }
            if ($profile->status === 'published' || $profile->published_at !== null) {
                throw new InvalidArgumentException('Published profile URLs cannot be changed directly. Contact Jannayaks if a correction is required.');
            }
            if ($this->hasPermanentPersonalSlug($profile) && $this->normalizePersonalSlug((string) $profile->slug) !== $this->normalizePersonalSlug($rawSlug)) {
                throw new InvalidArgumentException('Your personal URL is permanent once chosen. Contact Jannayaks if a correction is required.');
            }
        }

        $validation = $this->validatePersonalSlugCandidate(
            $rawSlug,
            (int) $profile->id,
            (string) $profile->full_name,
            (string) $profile->profession,
        );
        if (! $validation['ok']) {
            throw new InvalidArgumentException($validation['error'] ?? 'Invalid personal URL.');
        }

        $newSlug = $validation['slug'];

        try {
            return DB::transaction(function () use ($profile, $newSlug, $actor) {
                /** @var Profile $locked */
                $locked = Profile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

                $current = strtolower((string) $locked->slug);
                if ($current === $newSlug) {
                    $this->reserveSlug($newSlug, $locked);

                    return $locked;
                }

                if ($this->isSlugTaken($newSlug, (int) $locked->id)) {
                    throw new InvalidArgumentException('That URL is not available.');
                }

                $previous = $locked->slug;
                $now = now();

                $locked->forceFill([
                    'previous_slug' => $previous,
                    'slug' => $newSlug,
                    'slug_changed_at' => $now,
                    'slug_generated_at' => $locked->slug_generated_at ?? $now,
                ])->save();

                // Authorized-change evidence for the public URL.
                $this->auditLogger->log(
                    action: 'profile_url.slug_changed',
                    subject: $locked,
                    before: ['slug' => $previous],
                    after: ['slug' => $newSlug, 'previous_slug' => $previous],
                    actor: $actor,
                );

                if (filled($previous) && strtolower((string) $previous) !== $newSlug) {
                    $this->reserveSlug((string) $previous, $locked, SlugReservation::SOURCE_HISTORICAL);
                    $this->recordRedirect($locked, (string) $previous, $newSlug);
                }

                $this->reserveSlug($newSlug, $locked);

                return $locked->fresh() ?? $locked;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new InvalidArgumentException('That URL is not available.');
            }

            throw $e;
        }
    }

    public function ensureDraftProfileForApplication(Application $application): Profile
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

        return DB::transaction(function () use ($application, $userId) {
            $profile = Profile::query()->where('user_id', $userId)->lockForUpdate()->first();
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

            $profile->ensureMandatoryGeography();

            return $profile;
        });
    }

    /**
     * Resolve a public path slug to a living Profile, or null if unknown.
     * Does not enforce publication — callers must gate visibility.
     */
    public function findProfileByPublicSlug(string $slug): ?Profile
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            return null;
        }

        $profile = Profile::query()
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->first();

        if ($profile) {
            return $profile;
        }

        $redirect = $this->findActiveRedirect($normalized);
        if ($redirect === null) {
            return null;
        }

        $redirectable = $redirect->redirectable;
        if ($redirectable instanceof Profile) {
            return $redirectable;
        }

        return null;
    }

    public function findActiveRedirect(string $slug): ?SlugRedirect
    {
        $normalized = strtolower(trim($slug));

        /** @var SlugRedirect|null $redirect */
        $redirect = SlugRedirect::query()
            ->whereRaw('LOWER(old_slug) = ?', [$normalized])
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        return $redirect;
    }

    public function canonicalPublicPath(Profile $profile): ?string
    {
        if (! filled($profile->slug)) {
            return null;
        }

        return '/'.$profile->slug;
    }

    public function canonicalPublicUrl(Profile $profile): ?string
    {
        $path = $this->canonicalPublicPath($profile);
        if ($path === null) {
            return null;
        }

        return url($path);
    }

    public function isPubliclyVisible(Profile $profile): bool
    {
        if ($profile->status !== 'published') {
            return false;
        }

        if ($profile->published_at === null) {
            return false;
        }

        if ($profile->unpublished_at !== null) {
            return false;
        }

        if ($profile->suspended_at !== null) {
            return false;
        }

        if ($profile->erasure_completed_at !== null) {
            return false;
        }

        return true;
    }

    public function generateUniqueEmergingSlug(): string
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $candidate = $this->randomEmergingSlug();
            if (! $this->isReserved($candidate) && ! $this->isSlugTaken($candidate)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Unable to allocate a unique profile URL. Please try again.');
    }

    private function randomEmergingSlug(): string
    {
        $alphabet = self::EMERGING_ALPHABET;
        $max = strlen($alphabet) - 1;
        $chars = '';

        for ($i = 0; $i < self::EMERGING_LENGTH; $i++) {
            $chars .= $alphabet[random_int(0, $max)];
        }

        return Str::upper($chars);
    }

    private function reserveSlug(string $slug, Profile $profile, string $source = SlugReservation::SOURCE_ASSIGNED): void
    {
        $normalized = $this->isEmergingSystemSlug($slug) ? $slug : strtolower($slug);

        $existing = SlugReservation::query()
            ->whereRaw('LOWER(slug) = ?', [strtolower($normalized)])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ((int) $existing->profile_id !== (int) $profile->id) {
                throw new InvalidArgumentException('That URL is not available.');
            }

            return;
        }

        SlugReservation::query()->create([
            'slug' => $normalized,
            'profile_id' => $profile->id,
            'application_id' => $profile->application?->id,
            'source' => $source,
        ]);
    }

    private function recordRedirect(Profile $profile, string $oldSlug, string $newSlug): void
    {
        $expiryDays = (int) config('jannayaks.slug.redirect_expiry_days', 0);

        SlugRedirect::query()
            ->where('redirectable_type', $profile->getMorphClass())
            ->where('redirectable_id', $profile->id)
            ->update([
                'new_slug' => $newSlug,
                'updated_at' => now(),
            ]);

        $exists = SlugRedirect::query()
            ->whereRaw('LOWER(old_slug) = ?', [strtolower($oldSlug)])
            ->exists();

        if ($exists) {
            $owned = SlugRedirect::query()
                ->whereRaw('LOWER(old_slug) = ?', [strtolower($oldSlug)])
                ->where('redirectable_type', $profile->getMorphClass())
                ->where('redirectable_id', $profile->id)
                ->exists();

            if (! $owned) {
                throw new InvalidArgumentException('That historical URL cannot be reused.');
            }

            SlugRedirect::query()
                ->whereRaw('LOWER(old_slug) = ?', [strtolower($oldSlug)])
                ->where('redirectable_type', $profile->getMorphClass())
                ->where('redirectable_id', $profile->id)
                ->update([
                    'new_slug' => $newSlug,
                    'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
                    'updated_at' => now(),
                ]);

            return;
        }

        SlugRedirect::query()->create([
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
            'redirectable_type' => $profile->getMorphClass(),
            'redirectable_id' => $profile->id,
            'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = $e->getMessage();

        return $sqlState === '23505'
            || str_contains($message, 'Unique violation')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'UNIQUE constraint failed');
    }

    /**
     * Resolve package tier for a profile from its linked living application (preferred) or membership.
     */
    public function resolvePackageTier(Profile $profile): ?string
    {
        $application = Application::query()
            ->where('profile_id', $profile->id)
            ->orderByDesc('id')
            ->first();

        if ($application && filled($application->package_tier)) {
            return strtolower((string) $application->package_tier);
        }

        $membership = $profile->membership;
        if ($membership && filled($membership->tier)) {
            return strtolower((string) $membership->tier);
        }

        return null;
    }
}

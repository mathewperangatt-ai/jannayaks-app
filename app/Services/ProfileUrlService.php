<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Models\SlugRedirect;
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

    /**
     * Ensure a published living profile has a canonical slug.
     * Emerging (and A/D before personal selection) receive a 6-character system URL.
     */
    public function assignInitialCanonicalSlug(Profile $profile, string $packageTier): Profile
    {
        if ($packageTier === 'in_memoriam') {
            throw new InvalidArgumentException('In Memoriam profiles are outside living profile URL assignment.');
        }

        return DB::transaction(function () use ($profile) {
            /** @var Profile $locked */
            $locked = Profile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if (filled($locked->slug)) {
                return $locked;
            }

            $slug = $this->generateUniqueEmergingSlug();
            $now = now();

            $locked->forceFill([
                'slug' => $slug,
                'slug_generated_at' => $now,
                'slug_changed_at' => $now,
            ])->save();

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

    /**
     * @return array{ok: bool, slug?: string, error?: string}
     */
    public function validatePersonalSlugCandidate(string $raw, ?int $ignoreProfileId = null): array
    {
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

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return ['ok' => false, 'error' => 'Use lowercase letters, numbers, and single hyphens only (e.g. mathew-perangatt).'];
        }

        if (strlen($slug) < 3 || strlen($slug) > 64) {
            return ['ok' => false, 'error' => 'Personal URLs must be between 3 and 64 characters.'];
        }

        if ($this->isReserved($slug)) {
            return ['ok' => false, 'error' => 'That URL is reserved by the system.'];
        }

        if ($this->isSlugTaken($slug, $ignoreProfileId)) {
            return ['ok' => false, 'error' => 'That URL is not available.'];
        }

        return ['ok' => true, 'slug' => $slug];
    }

    public function isReserved(string $slug): bool
    {
        $normalized = strtolower(trim($slug));
        $reserved = array_map('strtolower', (array) config('jannayaks.slug.reserved', []));

        return in_array($normalized, $reserved, true);
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

        return SlugRedirect::query()
            ->whereRaw('LOWER(old_slug) = ?', [$normalized])
            ->exists();
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
        }

        $validation = $this->validatePersonalSlugCandidate($rawSlug, (int) $profile->id);
        if (! $validation['ok']) {
            throw new InvalidArgumentException($validation['error'] ?? 'Invalid personal URL.');
        }

        $newSlug = $validation['slug'];

        try {
            return DB::transaction(function () use ($profile, $newSlug) {
                /** @var Profile $locked */
                $locked = Profile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

                $current = strtolower((string) $locked->slug);
                if ($current === $newSlug) {
                    return $locked;
                }

                // Re-check under lock for race safety; DB unique indexes remain final authority.
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

                if (filled($previous) && strtolower((string) $previous) !== $newSlug) {
                    $this->recordRedirect($locked, (string) $previous, $newSlug);
                }

                return $locked->fresh() ?? $locked;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new InvalidArgumentException('That URL is not available.');
            }

            throw $e;
        }
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

        return '/p/'.$profile->slug;
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

        // Store Emerging identifiers in a stable uppercase form (example: A7K2M9).
        return Str::upper($chars);
    }

    private function recordRedirect(Profile $profile, string $oldSlug, string $newSlug): void
    {
        $expiryDays = (int) config('jannayaks.slug.redirect_expiry_days', 0);

        // Point all prior history rows for this profile at the new canonical slug.
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
            // Never reassign a historical URL that already belongs to someone.
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

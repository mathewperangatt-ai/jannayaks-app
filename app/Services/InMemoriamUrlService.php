<?php

namespace App\Services;

use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Models\SlugRedirect;
use App\Models\SlugReservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InMemoriamUrlService
{
    public const LATE_PREFIX = 'late.';

    public function __construct(
        private StaffAuditLogger $audit,
        private PersonalSlugGenerator $personalSlugs,
    ) {}

    /**
     * Canonical memorial slug: exactly one `late.` prefix on the name stem.
     * Repeated prefixes collapse. A leftover `.late` suffix is removed before
     * the prefix is applied. Living-profile assignment never calls this.
     */
    public function withLatePrefix(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $prefix = self::LATE_PREFIX;

        while (str_ends_with($slug, '.late')) {
            $slug = rtrim(substr($slug, 0, -strlen('.late')), '.');
        }

        while (str_starts_with($slug, $prefix)) {
            $slug = substr($slug, strlen($prefix));
        }

        $slug = trim($slug, '.');
        if ($slug === '') {
            return '';
        }

        return $prefix.$slug;
    }

    /**
     * @return array{ok: bool, slug?: string, error?: string}
     */
    public function validateSlugCandidate(string $raw, ?int $ignoreMemorialId = null): array
    {
        if ($raw !== '' && preg_match('/\s/', $raw) === 1) {
            return ['ok' => false, 'error' => 'A valid memorial URL is required (no spaces).'];
        }

        $slug = $this->withLatePrefix($raw);

        if ($slug === '') {
            return ['ok' => false, 'error' => 'A valid memorial URL is required (no spaces).'];
        }

        if (preg_match('/^late\.[a-z0-9]+(?:[.\-][a-z0-9]+)*$/', $slug) !== 1) {
            return ['ok' => false, 'error' => 'Use lowercase letters, numbers, dots, or single hyphens. The late. prefix is added automatically.'];
        }

        $stem = substr($slug, strlen(self::LATE_PREFIX));
        if (strlen($stem) < 3 || strlen($slug) > 64) {
            return ['ok' => false, 'error' => 'Memorial URLs must be between 3 and 64 characters.'];
        }

        if ($this->isReserved($slug) || $this->isReserved($stem)) {
            return ['ok' => false, 'error' => 'That URL is reserved by the system.'];
        }

        if ($this->isSlugTaken($slug, $ignoreMemorialId)) {
            return ['ok' => false, 'error' => 'That URL is not available.'];
        }

        return ['ok' => true, 'slug' => $slug];
    }

    /**
     * Name-derived memorial slug beginning with exactly one `late.` prefix.
     * The natural name is used first. A numbered variation is used only when
     * that address is already taken.
     *
     * @throws ValidationException
     */
    public function generateCanonicalSlug(InMemoriamProfile $profile): string
    {
        $combinations = $this->personalSlugs->generateCombinations((string) $profile->deceased_full_name);
        $natural = $combinations[0] ?? null;
        if (! is_string($natural) || $natural === '') {
            throw ValidationException::withMessages([
                'slug' => 'A memorial URL derived from the name could not be assigned.',
            ]);
        }

        $candidates = [$natural];
        foreach ($this->personalSlugs->collisionSuffixes((int) $profile->id) as $number) {
            $candidates[] = $this->personalSlugs->collisionCandidate($natural, $number);
        }

        foreach ($candidates as $candidate) {
            $validation = $this->validateSlugCandidate($candidate, (int) $profile->id);
            if ($validation['ok'] === true && isset($validation['slug'])) {
                return $validation['slug'];
            }
        }

        throw ValidationException::withMessages([
            'slug' => 'A memorial URL derived from the name could not be assigned.',
        ]);
    }

    public function isReserved(string $slug): bool
    {
        return app(ProfileUrlService::class)->isReserved($slug);
    }

    public function isSlugTaken(string $slug, ?int $ignoreMemorialId = null, ?int $ignoreProfileId = null): bool
    {
        $normalized = strtolower($slug);

        $memorial = InMemoriamProfile::query()->whereRaw('LOWER(slug) = ?', [$normalized]);
        if ($ignoreMemorialId !== null) {
            $memorial->whereKeyNot($ignoreMemorialId);
        }
        if ($memorial->exists()) {
            return true;
        }

        $profile = Profile::query()->whereRaw('LOWER(slug) = ?', [$normalized]);
        if ($ignoreProfileId !== null) {
            $profile->whereKeyNot($ignoreProfileId);
        }
        if ($profile->exists()) {
            return true;
        }

        if (SlugRedirect::query()->whereRaw('LOWER(old_slug) = ?', [$normalized])->exists()) {
            return true;
        }

        if ($ignoreMemorialId !== null) {
            $owned = InMemoriamProfile::query()->whereKey($ignoreMemorialId)->value('slug');
            if (is_string($owned) && strtolower($owned) === $normalized) {
                return false;
            }
        }

        return SlugReservation::query()
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    public function assignSlug(InMemoriamProfile $profile, string $rawSlug, User $actor): InMemoriamProfile
    {
        if (! $actor->canManageEditorial()) {
            throw ValidationException::withMessages([
                'slug' => 'You are not allowed to assign memorial URLs.',
            ]);
        }

        if ($profile->is_sealed && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'slug' => 'Sealed memorial URLs cannot be changed routinely.',
            ]);
        }

        $validation = $this->validateSlugCandidate($rawSlug, (int) $profile->id);
        if (! $validation['ok']) {
            throw ValidationException::withMessages([
                'slug' => $validation['error'] ?? 'Invalid memorial URL.',
            ]);
        }

        $newSlug = $validation['slug'];

        try {
            return DB::transaction(function () use ($profile, $newSlug, $actor): InMemoriamProfile {
                /** @var InMemoriamProfile $locked */
                $locked = InMemoriamProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
                $current = strtolower((string) ($locked->slug ?? ''));
                if ($current === $newSlug) {
                    return $locked;
                }

                if ($this->isSlugTaken($newSlug, (int) $locked->id)) {
                    throw ValidationException::withMessages([
                        'slug' => 'That URL is not available.',
                    ]);
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
                    SlugRedirect::query()->create([
                        'redirectable_type' => $locked->getMorphClass(),
                        'redirectable_id' => $locked->id,
                        'old_slug' => (string) $previous,
                        'new_slug' => $newSlug,
                    ]);
                    SlugReservation::query()->insertOrIgnore([
                        'slug' => (string) $previous,
                        'profile_id' => null,
                        'application_id' => null,
                        'source' => SlugReservation::SOURCE_IN_MEMORIAM,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                SlugReservation::query()->insertOrIgnore([
                    'slug' => $newSlug,
                    'profile_id' => null,
                    'application_id' => null,
                    'source' => SlugReservation::SOURCE_IN_MEMORIAM,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->audit->log(
                    action: 'in_memoriam.slug_assigned',
                    subject: $locked,
                    before: ['slug' => $previous],
                    after: ['slug' => $newSlug],
                    actor: $actor,
                );

                return $locked->fresh() ?? $locked;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ValidationException::withMessages([
                    'slug' => 'That URL is not available.',
                ]);
            }

            throw $e;
        }
    }

    public function findByPublicSlug(string $raw): ?InMemoriamProfile
    {
        $slug = strtolower(trim($raw));
        if ($slug === '') {
            return null;
        }

        $profile = InMemoriamProfile::query()->whereRaw('LOWER(slug) = ?', [$slug])->first();
        if ($profile instanceof InMemoriamProfile) {
            return $profile;
        }

        $redirect = SlugRedirect::query()
            ->whereRaw('LOWER(old_slug) = ?', [$slug])
            ->where('redirectable_type', (new InMemoriamProfile)->getMorphClass())
            ->latest('id')
            ->first();

        $target = $redirect?->redirectable;

        return $target instanceof InMemoriamProfile ? $target : null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23505' || str_contains(strtolower($e->getMessage()), 'unique');
    }
}

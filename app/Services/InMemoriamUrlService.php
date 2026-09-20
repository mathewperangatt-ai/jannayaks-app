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
    public function __construct(private StaffAuditLogger $audit) {}

    /**
     * @return array{ok: bool, slug?: string, error?: string}
     */
    public function validateSlugCandidate(string $raw, ?int $ignoreMemorialId = null): array
    {
        $slug = strtolower(trim($raw));

        if ($slug === '' || preg_match('/\s/', $raw) === 1) {
            return ['ok' => false, 'error' => 'A valid memorial URL is required (no spaces).'];
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return ['ok' => false, 'error' => 'Use lowercase letters, numbers, and single hyphens only.'];
        }

        if (strlen($slug) < 3 || strlen($slug) > 64) {
            return ['ok' => false, 'error' => 'Memorial URLs must be between 3 and 64 characters.'];
        }

        if ($this->isReserved($slug)) {
            return ['ok' => false, 'error' => 'That URL is reserved by the system.'];
        }

        if ($this->isSlugTaken($slug, $ignoreMemorialId)) {
            return ['ok' => false, 'error' => 'That URL is not available.'];
        }

        return ['ok' => true, 'slug' => $slug];
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

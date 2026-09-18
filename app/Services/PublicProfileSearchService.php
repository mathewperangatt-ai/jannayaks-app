<?php

namespace App\Services;

use App\Models\EditorialContent;
use App\Models\GeoDistrict;
use App\Models\GeoState;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicProfileSearchService
{
    public const PER_PAGE = 12;

    /**
     * Gallery: published profiles only, newest published first (deterministic, non-evaluative).
     *
     * @param  array{district_id?: int|null, state?: string|null, country?: string|null}  $filters
     */
    public function gallery(array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->publishedBaseQuery()
            ->with([
                'geography.district.state',
                'geography.localBody',
                'geography.ward',
                'publicOffices',
                'media',
            ]);

        $this->applyOptionalFilters($query, $filters);

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Primary text search over published public fields. Multi-word queries AND each term.
     * Ranking uses PostgreSQL ts_rank when available; scores are never exposed publicly.
     *
     * @param  array{district_id?: int|null, state?: string|null, country?: string|null}  $filters
     */
    public function search(string $rawQuery, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $terms = $this->tokenize($rawQuery);

        $query = $this->publishedBaseQuery()
            ->with([
                'geography.district.state',
                'geography.localBody',
                'geography.ward',
                'publicOffices',
                'media',
            ]);

        $this->applyOptionalFilters($query, $filters);

        if ($terms === []) {
            return $query
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        foreach ($terms as $term) {
            $like = '%'.$this->escapeLike($term).'%';
            $query->where(function (Builder $outer) use ($like): void {
                $outer->where('profiles.full_name', 'ilike', $like)
                    ->orWhere('profiles.display_name', 'ilike', $like)
                    ->orWhere('profiles.profession', 'ilike', $like)
                    ->orWhere('profiles.bio_headline', 'ilike', $like)
                    ->orWhereHas('geography', function (Builder $geo) use ($like): void {
                        $geo->where('locality_place', 'ilike', $like)
                            ->orWhere('state_region_name', 'ilike', $like)
                            ->orWhere('country_code', 'ilike', $like)
                            ->orWhere('postal_code', 'ilike', $like)
                            ->orWhereHas('district', function (Builder $d) use ($like): void {
                                $d->where('name', 'ilike', $like)
                                    ->orWhereHas('state', fn (Builder $s) => $s->where('name', 'ilike', $like)
                                        ->orWhere('country_name', 'ilike', $like));
                            })
                            ->orWhereHas('localBody', fn (Builder $lb) => $lb->where('name', 'ilike', $like))
                            ->orWhereHas('ward', fn (Builder $w) => $w->where('name', 'ilike', $like));
                    })
                    ->orWhereHas('publicOffices', function (Builder $office) use ($like): void {
                        $office->where('office_name', 'ilike', $like)
                            ->orWhere('where_location', 'ilike', $like)
                            ->orWhere('term_summary', 'ilike', $like);
                    })
                    ->orWhereHas('editorialContents', function (Builder $editorial) use ($like): void {
                        $editorial->where('status', EditorialContent::STATUS_APPROVED)
                            ->where(function (Builder $e) use ($like): void {
                                $e->where('title', 'ilike', $like)
                                    ->orWhere('summary', 'ilike', $like)
                                    ->orWhere('body', 'ilike', $like);
                            });
                    });
            });
        }

        // Deterministic ordering after match; do not expose relevance scores.
        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $rawQuery): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $rawQuery) ?? '');
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $raw) ?: [];
        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }
            $terms[] = mb_substr($part, 0, 64);
        }

        return array_values(array_unique($terms));
    }

    /**
     * @return Builder<Profile>
     */
    public function publishedBaseQuery(): Builder
    {
        return Profile::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNull('unpublished_at')
            ->whereNull('suspended_at')
            ->whereNull('erasure_completed_at')
            ->whereNotNull('slug');
    }

    /**
     * @param  Builder<Profile>  $query
     * @param  array{district_id?: int|null, state?: string|null, country?: string|null}  $filters
     */
    private function applyOptionalFilters(Builder $query, array $filters): void
    {
        $districtId = isset($filters['district_id']) ? (int) $filters['district_id'] : 0;
        if ($districtId > 0) {
            $query->whereHas('geography', fn (Builder $g) => $g->where('district_id', $districtId));
        }

        $state = isset($filters['state']) ? trim((string) $filters['state']) : '';
        if ($state !== '') {
            $query->whereHas('geography', function (Builder $g) use ($state): void {
                $g->where('state_region_name', 'ilike', $state)
                    ->orWhereHas('district.state', function (Builder $s) use ($state): void {
                        $s->where('name', 'ilike', $state)
                            ->orWhere('code', 'ilike', $state);
                    });
            });
        }

        $country = isset($filters['country']) ? trim((string) $filters['country']) : '';
        if ($country !== '') {
            $query->whereHas('geography', function (Builder $g) use ($country): void {
                $g->where('country_code', 'ilike', $country)
                    ->orWhereHas('district.state', fn (Builder $s) => $s->where('country_name', 'ilike', $country));
            });
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function districtOptions(): array
    {
        return GeoDistrict::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (GeoDistrict $d): array => ['id' => (int) $d->id, 'name' => (string) $d->name])
            ->all();
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function stateOptions(): array
    {
        return GeoState::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (GeoState $s): array => ['code' => (string) $s->code, 'name' => (string) $s->name])
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Profile;
use Illuminate\Support\Collection;

/**
 * SEO-3: XML sitemap entry generation.
 *
 * Scope: static public pages + genuinely public profiles. Deliberately excludes /search (utility page, not an SEO surface)
 * and demonstration content.
 *
 * Demo/temporary content discriminator — the convention already used by
 * PublicProfileUrlController (isDemonstration) and DemoProfilesSeeder:
 * demonstration records are owned by / commissioner-listed under the
 * internal @jannayaks.internal email domain. If a durable is_demo flag is
 * ever wanted, that is a future migration decision — not invented here.
 *
 * The entry list is a simple collection today; extend or chunk inside
 * entries() if the site ever grows to sitemap-index scale.
 */
class SitemapService
{
    /** Internal demonstration email domain (existing convention). */
    public const DEMO_EMAIL_DOMAIN = '@jannayaks.internal';

    public function __construct(
        private PublicProfileSearchService $profileSearch,
        private ProfileUrlService $profileUrls,
    ) {}

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    public function entries(): Collection
    {
        return collect()
            ->merge($this->staticEntries())
            ->merge($this->profileEntries())
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function staticEntries(): Collection
    {
        $entries = collect([
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('gallery.index'), 'lastmod' => null],
            ['loc' => route('recommend.show'), 'lastmod' => null],
            ['loc' => route('invitation-request.show'), 'lastmod' => null],
            ['loc' => route('faq-charges'), 'lastmod' => null],
        ]);

        return $entries->values();
    }

    /**
     * Public profiles only — the existing publication rules — excluding
     * demonstration profiles via the established internal-email marker.
     *
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function profileEntries(): Collection
    {
        return $this->profileSearch
            ->publishedBaseQuery()
            ->whereDoesntHave('user', fn ($q) => $q->where('email', 'like', '%'.self::DEMO_EMAIL_DOMAIN))
            ->orderBy('published_at')
            ->get(['id', 'slug', 'published_at'])
            ->map(fn (Profile $profile): array => [
                // Existing canonical URL service — no manual concatenation.
                'loc' => (string) $this->profileUrls->canonicalPublicUrl($profile),
                'lastmod' => $profile->published_at?->toAtomString(),
            ])
            ->filter(fn (array $entry): bool => $entry['loc'] !== '');
    }
}

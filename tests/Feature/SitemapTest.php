<?php

namespace Tests\Feature;

use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Models\User;
use App\Services\InMemoriamLifecycleService;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO-3: /sitemap.xml — static public pages, genuinely public profiles and
 * publicly visible memorials only. /search never appears; demonstration
 * content (the established @jannayaks.internal marker) never appears;
 * no query strings; absolute canonical-form URLs.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_well_formed_xml_with_correct_content_type(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'sitemap must be well-formed XML');
        $this->assertStringContainsString('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $response->getContent());
    }

    public function test_sitemap_lists_static_public_pages_but_never_search(): void
    {
        $locs = $this->fetchLocs();

        $home = rtrim(route('home'), '/');
        foreach ([
            $home,
            $home.'/gallery',
            $home.'/in-memoriam',
            $home.'/recommend',
            $home.'/request-invitation',
            $home.'/faq-charges',
        ] as $expected) {
            $this->assertContains($expected, $locs);
        }

        $this->assertNotContains(url('/search'), $locs);
        $this->assertFalse(collect($locs)->contains(fn ($loc) => str_contains($loc, '/search')), 'no /search URLs at all');
    }

    public function test_sitemap_contains_genuinely_public_profiles_only(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $public = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'published',
            'full_name' => 'Public Person',
            'display_name' => 'Public Person',
            'profession' => 'Leader',
            'slug' => 'public.person',
            'published_at' => now(),
        ]);
        Profile::query()->create([
            'user_id' => $other->id,
            'status' => 'draft',
            'full_name' => 'Hidden Person',
            'display_name' => 'Hidden Person',
            'slug' => 'hidden.person',
        ]);

        $locs = $this->fetchLocs();

        $this->assertContains(url('/public.person'), $locs); // url() == canonicalPublicUrl generator
        $this->assertNotContains(url('/hidden.person'), $locs);
    }

    public function test_sitemap_contains_publicly_visible_memorials_only(): void
    {
        $admin = User::factory()->admin()->create();
        $memorial = InMemoriamProfile::factory()->paid()->create([
            'slug' => 'public-memorial-record',
            'status' => InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
        ]);
        $published = app(InMemoriamLifecycleService::class)->publish($memorial, $admin);

        // An unpublished memorial must stay out.
        InMemoriamProfile::factory()->paid()->create([
            'slug' => 'unpublished-memorial-record',
            'status' => InMemoriamProfile::STATUS_DRAFT,
        ]);

        $locs = $this->fetchLocs();

        $this->assertContains(route('in-memoriam.show', ['slug' => $published->slug]), $locs);
        $this->assertNotContains(route('in-memoriam.show', ['slug' => 'unpublished-memorial-record']), $locs);
    }

    public function test_demonstration_content_is_excluded_from_sitemap(): void
    {
        // The demo set satisfies the generic "published" conditions but must
        // never be suggested to search engines (fictional identities).
        $this->seed(DemoProfilesSeeder::class);

        $locs = $this->fetchLocs();

        $this->assertNotContains(url('/t.gopalakrishnan'), $locs);
        $this->assertNotContains(route('in-memoriam.show', ['slug' => 'k.v.mathew']), $locs);
        $this->assertNotContains(route('in-memoriam.show', ['slug' => 'dr.saroja.nair']), $locs);
        $this->assertFalse(collect($locs)->contains(fn ($loc) => str_contains($loc, 'gopalakrishnan')));
    }

    public function test_all_urls_are_absolute_canonical_form_without_query_strings_and_unique(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $locs = $this->fetchLocs();

        $this->assertGreaterThanOrEqual(6, count($locs));
        foreach ($locs as $loc) {
            $this->assertStringStartsWith('http', $loc, $loc);
            $this->assertStringNotContainsString('?', $loc, $loc.' must carry no query string');
            $this->assertStringNotContainsString('&', $loc, $loc);
        }
        $this->assertSame(count($locs), count(array_unique($locs)), 'no duplicate URLs');
    }

    /**
     * @return list<string>
     */
    private function fetchLocs(): array
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());
        $this->assertNotFalse($xml);

        // Plain loop: iterator_to_array would collapse SimpleXML's duplicate keys.
        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        return $locs;
    }
}

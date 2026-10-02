<?php

namespace Tests\Feature;

use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO-1: dynamic meta descriptions + Open Graph / Twitter metadata.
 * Indexing directives are NOT touched here: every public page must keep
 * its construction-stage noindex, nofollow.
 */
class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_description_og_and_twitter_with_noindex_intact(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $content = $response->getContent();

        // Description present, no visible-copy change, noindex intact.
        $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]+">#i', $content);
        $this->assertStringContainsString('digital biographical archive', $content);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);

        // OG + Twitter.
        $this->assertStringContainsString('property="og:site_name" content="Jannayaks"', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:title" content="[^"]+">#i', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:description" content="[^"]+">#i', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:url" content="[^"]+">#i', $content);
        $this->assertStringContainsString('property="og:type" content="website"', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]+/branding/jannayaks-logo-approved\.jpg">#i', $content);
        $this->assertStringContainsString('name="twitter:card" content="summary"', $content);
        $this->assertMatchesRegularExpression('#<meta name="twitter:title" content="[^"]+">#i', $content);
        $this->assertMatchesRegularExpression('#<meta name="twitter:image" content="[^"]+/branding/jannayaks-logo-approved\.jpg">#i', $content);
    }

    /**
     * SEO-2: every static public page emits exactly one self-referencing
     * canonical — the clean route() base URL, absolute, never carrying a
     * query string. noindex and SEO-1 metadata stay intact.
     */
    public function test_static_pages_emit_single_query_free_canonical(): void
    {
        foreach ([
            '/' => '/',
            '/gallery?foo=bar' => '/gallery',
            '/search?q=mathew' => '/search',
            '/in-memoriam' => '/in-memoriam',
            '/recommend' => '/recommend',
            '/request-invitation' => '/request-invitation',
            '/faq-charges' => '/faq-charges',
        ] as $path => $clean) {
            $response = $this->get($path);
            $response->assertOk();
            $content = $response->getContent();

            $canonicalCount = substr_count($content, 'rel="canonical"');
            $this->assertSame(1, $canonicalCount, $path.' must have exactly one canonical');

            $expected = rtrim(url($clean), '/');
            $this->assertMatchesRegularExpression(
                '#<link rel="canonical" href="'.preg_quote($expected, '#').'">#i',
                $content,
                $path.' canonical must be the clean absolute base URL'
            );
            $this->assertStringNotContainsString('rel="canonical" href="?', $content, $path);
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content, $path);
            $this->assertStringContainsString('property="og:title"', $content, $path);
        }
    }

    public function test_profile_and_memorial_keep_single_canonical(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        foreach (['/t.gopalakrishnan', '/in-memoriam/k.v.mathew'] as $path) {
            $content = $this->get($path)->getContent();
            $this->assertSame(1, substr_count($content, 'rel="canonical"'), $path);
            $this->assertMatchesRegularExpression(
                '#<link rel="canonical" href="[^"]+'.preg_quote($path, '#').'">#i',
                $content,
                $path
            );
        }
    }

    public function test_profile_page_seo_uses_editorial_summary_and_canonical(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $response = $this->get('/t.gopalakrishnan');
        $response->assertOk();
        $content = $response->getContent();

        // Description from the stored editorial summary (verbatim, single-escaped).
        $this->assertStringContainsString(
            'name="description" content="T. Gopalakrishnan&#039;s public life has developed',
            $content
        );

        // OG matches the profile; canonical/og:url agree.
        $this->assertMatchesRegularExpression('#<meta property="og:url" content="[^"]+/t\.gopalakrishnan">#i', $content);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]+/t\.gopalakrishnan">#i', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:title" content="T\. Gopalakrishnan — Jannayaks">#i', $content);
        $this->assertStringContainsString('property="og:type" content="profile"', $content);

        // Twitter card present; noindex unchanged.
        $this->assertStringContainsString('name="twitter:card" content="summary"', $content);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);
    }

    public function test_memorial_page_seo_uses_memorial_metadata(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $response = $this->get('/in-memoriam/k.v.mathew');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]+">#i', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:url" content="[^"]+/in-memoriam/k\.v\.mathew">#i', $content);
        $this->assertMatchesRegularExpression('#<meta property="og:title" content="K\. V\. Mathew — In Memoriam — Jannayaks">#i', $content);
        $this->assertStringContainsString('property="og:type" content="profile"', $content);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);
    }

    public function test_static_pages_have_descriptions_and_social_tags(): void
    {
        foreach ([
            '/gallery' => 'Browse published Jannayaks profiles',
            '/in-memoriam' => 'In Memoriam on Jannayaks',
            '/recommend' => 'Recommend someone whose life',
            '/request-invitation' => 'Request an invitation to create a Jannayaks profile',
            '/faq-charges' => 'Jannayaks membership charges',
        ] as $path => $fragment) {
            $response = $this->get($path);
            $response->assertOk();
            $content = $response->getContent();

            $this->assertStringContainsString('name="description" content="'.$fragment, $content, $path);
            $this->assertStringContainsString('property="og:title"', $content, $path);
            $this->assertStringContainsString('name="twitter:card"', $content, $path);
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content, $path);
        }
    }

    public function test_search_page_stays_noindex_with_static_description(): void
    {
        // Query-driven descriptions would create snippet pages; the search
        // page uses one static description and keeps noindex, nofollow.
        $response = $this->get('/search?q=Gopalakrishnan');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('name="description" content="Search published Jannayaks profiles by name, area or PIN code."', $content);
        $this->assertStringNotContainsString('Gopalakrishnan" name="description"', $content);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);
        $this->assertMatchesRegularExpression('#<title>Search: Gopalakrishnan — Search — Jannayaks</title>#i', $content);
    }
}

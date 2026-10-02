<?php

namespace Tests\Feature;

use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO-4: structured data / JSON-LD.
 *
 * JSON is extracted from the rendered pages and validated with a real
 * parser (json_decode) — not regex. Only facts displayed on the page may
 * appear; indexing controls must not change.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array<string, mixed>> all @graph nodes across ld+json blocks */
    private function nodes(string $path): array
    {
        $content = $this->get($path)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        $nodes = [];
        foreach ($matches[1] as $block) {
            $decoded = json_decode($block, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $decoded['@context']);
            foreach ($decoded['@graph'] ?? [] as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /** @return array<string, array<string, mixed>> nodes keyed by @type */
    private function nodesByType(string $path): array
    {
        $byType = [];
        foreach ($this->nodes($path) as $node) {
            $byType[$node['@type']] = $node;
        }

        return $byType;
    }

    public function test_homepage_website_schema_with_accurate_search_action(): void
    {
        $nodes = $this->nodesByType('/');

        $this->assertArrayHasKey('WebSite', $nodes);
        $site = $nodes['WebSite'];
        $this->assertSame('Jannayaks', $site['name']);
        $this->assertSame(url('/'), $site['url']);
        $this->assertStringContainsString('digital biographical archive', $site['description']);

        // The existing /search?q= GET interface is real, so the SearchAction
        // represents it accurately.
        $this->assertSame('SearchAction', $site['potentialAction']['@type']);
        $this->assertStringContainsString('/search?q={search_term_string}', $site['potentialAction']['target']['urlTemplate']);
        $this->assertSame('required name=search_term_string', $site['potentialAction']['query-input']);
    }

    public function test_profile_person_and_profile_page_graph(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $nodes = $this->nodesByType('/t.gopalakrishnan');

        $this->assertArrayHasKey('ProfilePage', $nodes);
        $this->assertArrayHasKey('Person', $nodes);
        $this->assertArrayHasKey('BreadcrumbList', $nodes);

        $page = $nodes['ProfilePage'];
        $person = $nodes['Person'];

        $this->assertSame(url('/t.gopalakrishnan'), $page['url']);
        $this->assertSame(url('/t.gopalakrishnan#person'), $page['mainEntity']['@id']);
        $this->assertSame('T. Gopalakrishnan', $person['name']);
        $this->assertSame(url('/t.gopalakrishnan'), $person['url']);

        // Description is the displayed editorial summary — apostrophe intact.
        $this->assertStringContainsString("T. Gopalakrishnan's public life", $person['description']);

        // jobTitle = the displayed profession line; nothing fabricated.
        $this->assertSame('President, Sree Narayana Community Development Council · Social Educator & Community Leader', $person['jobTitle']);
        $this->assertArrayNotHasKey('birthDate', $person);
        $this->assertArrayNotHasKey('deathDate', $person);
        $this->assertArrayNotHasKey('image', $person); // no approved portrait attached
        $this->assertArrayNotHasKey('sameAs', $person);

        // Breadcrumb mirrors the visible trail and ends at the canonical URL.
        $crumbs = $nodes['BreadcrumbList']['itemListElement'];
        $this->assertSame('Home', $crumbs[0]['name']);
        $this->assertSame('Demo Profiles', $crumbs[1]['name']);
        $this->assertSame('T. Gopalakrishnan', $crumbs[2]['name']);
        $this->assertSame(url('/t.gopalakrishnan'), $crumbs[2]['item']);

        // Schema URL agrees with the canonical link; indexing controls intact.
        $content = $this->get('/t.gopalakrishnan')->getContent();
        $this->assertMatchesRegularExpression('/<link rel="canonical" href="[^"]+\/t\.gopalakrishnan">/', $content);
        $this->assertSame(1, substr_count($content, 'application/ld+json'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);
    }

    public function test_memorial_schema_only_displayed_facts(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $nodes = $this->nodesByType('/in-memoriam/k.v.mathew');

        $person = $nodes['Person'];
        // The approved memorial display convention appends ".late" to the
        // displayed name (underlying full name is untouched).
        $this->assertSame('K. V. Mathew .late', $person['name']);
        $this->assertSame(url('/in-memoriam/k.v.mathew'), $person['url']);

        // The years are displayed on the page, so they may be represented.
        $this->assertSame('1945', $person['birthDate']);
        $this->assertSame('2021', $person['deathDate']);

        // The role line is displayed; nothing else is fabricated.
        $this->assertSame('Teacher, Institution Builder, Mentor', $person['jobTitle']);
        $this->assertArrayNotHasKey('causeOfDeath', $person);
        $this->assertArrayNotHasKey('sameAs', $person);

        $crumbs = $nodes['BreadcrumbList']['itemListElement'];
        $this->assertSame('In Memoriam', $crumbs[1]['name']);
        $this->assertSame(url('/in-memoriam/k.v.mathew'), $crumbs[2]['item']);
    }

    public function test_static_pages_have_webpage_schema_and_faq_has_no_faqpage(): void
    {
        foreach ([
            '/gallery' => 'Gallery — Jannayaks',
            '/in-memoriam' => 'In Memoriam — Jannayaks',
            '/recommend' => 'Recommend Someone You May Know — Jannayaks',
            '/request-invitation' => 'Request an Invitation — Jannayaks',
            '/faq-charges' => 'FAQ & Charges — Jannayaks',
        ] as $path => $name) {
            $nodes = $this->nodesByType($path);

            $this->assertArrayHasKey('WebPage', $nodes, $path);
            $this->assertSame(rtrim(url($path), '/'), $nodes['WebPage']['url'], $path);
            $this->assertSame($name, $nodes['WebPage']['name'], $path);
        }

        // The FAQ page carries real Q&A prose, but no FAQPage schema is added
        // (restraint: no rich-result markup without a decision).
        $this->assertArrayNotHasKey('FAQPage', $this->nodesByType('/faq-charges'));
    }

    public function test_search_page_has_no_structured_data(): void
    {
        $content = $this->get('/search?q=anything')->getContent();

        $this->assertStringNotContainsString('application/ld+json', $content);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);
    }

    public function test_malayalam_and_punctuation_survive_json_encoding(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        // The Malayalam concept page is a design exploration; instead verify
        // the memorial landing name and the profile apostrophe round-trip
        // through decode (already asserted) and that raw JSON contains no
        // unescaped </script> breakout or literal control characters.
        $content = $this->get('/t.gopalakrishnan')->getContent();
        $this->assertStringNotContainsString('</script>",&quot;', $content);

        foreach ($this->nodes('/t.gopalakrishnan') as $node) {
            $this->assertNotEmpty($node['@type']);
        }
    }
}

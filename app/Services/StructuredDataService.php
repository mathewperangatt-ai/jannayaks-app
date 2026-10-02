<?php

namespace App\Services;

/**
 * SEO-4: central Schema.org JSON-LD builder.
 *
 * Conservative by design: nodes carry only facts already displayed on the
 * public page. Nothing speculative (no ratings, affiliations, social
 * profiles, addresses, dates that are not shown).
 *
 * encode() uses hex-escaping for <, >, &, ' and " so the JSON is safe to
 * embed inside a <script type="application/ld+json"> block (no </script>
 * breakout, apostrophes/quotes/Malayalam survive decoding) and remains
 * valid JSON for any parser.
 */
class StructuredDataService
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /** Encode a @graph array into embeddable JSON-LD. */
    public function encode(array $graph): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($graph)],
            self::JSON_FLAGS
        );
    }

    /** Homepage WebSite node; SearchAction only when the GET search URL is real. */
    public function websiteGraph(string $description, ?string $searchActionUrl = null): array
    {
        $url = url('/');

        $node = [
            '@type' => 'WebSite',
            '@id' => $url.'#website',
            'url' => $url,
            'name' => (string) config('jannayaks.seo.site_name', 'Jannayaks'),
            'description' => $description,
        ];

        if ($searchActionUrl !== null) {
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchActionUrl.'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return [$node];
    }

    /** Minimal accurate WebPage node for a static public page. */
    public function webPageGraph(string $url, string $name): array
    {
        return [
            [
                '@type' => 'WebPage',
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $name,
            ],
        ];
    }

    /**
     * Profile / memorial page graph: ProfilePage + Person (+ visible
     * breadcrumbs). Only non-null facts are emitted.
     *
     * @param  array<int, array{0: string, 1: string}>  $crumbs  visible breadcrumb [name, url] pairs
     */
    public function profileGraph(
        string $url,
        string $name,
        ?string $description = null,
        ?string $imageUrl = null,
        ?string $jobTitle = null,
        array $crumbs = [],
        ?string $birthYear = null,
        ?string $deathYear = null,
    ): array {
        $person = [
            '@type' => 'Person',
            '@id' => $url.'#person',
            'name' => $name,
            'url' => $url,
        ];
        if ($description !== null && $description !== '') {
            $person['description'] = $description;
        }
        if ($imageUrl !== null && $imageUrl !== '') {
            $person['image'] = $imageUrl;
        }
        if ($jobTitle !== null && $jobTitle !== '') {
            $person['jobTitle'] = $jobTitle;
        }
        if ($birthYear !== null && $birthYear !== '') {
            $person['birthDate'] = $birthYear;
        }
        if ($deathYear !== null && $deathYear !== '') {
            $person['deathDate'] = $deathYear;
        }

        $graph = [
            [
                '@type' => 'ProfilePage',
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $name,
                'mainEntity' => ['@id' => $url.'#person'],
            ],
            $person,
        ];

        if (count($crumbs) >= 2) {
            $graph[] = $this->breadcrumb($crumbs);
        }

        return $graph;
    }

    /** BreadcrumbList mirroring the page's visible breadcrumb trail. */
    public function breadcrumb(array $items): array
    {
        $elements = [];
        $position = 1;
        foreach ($items as [$name, $url]) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}

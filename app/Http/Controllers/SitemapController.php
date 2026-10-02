<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * SEO-3: serves the dynamic XML sitemap at /sitemap.xml.
 *
 * Generated per request so newly published/removed public profiles and
 * memorials are reflected automatically. application/xml content type.
 */
class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemap): Response
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($sitemap->entries() as $entry) {
            $lines[] = '    <url>';
            $lines[] = '        <loc>'.htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
            if ($entry['lastmod'] !== null) {
                $lines[] = '        <lastmod>'.$entry['lastmod'].'</lastmod>';
            }
            $lines[] = '    </url>';
        }
        $lines[] = '</urlset>';

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}

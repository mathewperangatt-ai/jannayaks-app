<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use App\Services\SitemapService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(
        PublicProfileSearchService $search,
        PublicProfilePresentationService $presentation,
    ): View {
        // Section 2 uses real seeded demonstration records only — never invented people.
        $demoProfiles = $search->publishedBaseQuery()
            ->whereHas('user', fn ($query) => $query->where('email', 'like', '%'.SitemapService::DEMO_EMAIL_DOMAIN))
            ->with(['media', 'application', 'user'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        return view('home.index', [
            'demoProfiles' => $demoProfiles,
            'presentation' => $presentation,
        ]);
    }
}

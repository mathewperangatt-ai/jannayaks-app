<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Review listing of the seeded demonstration records: living profiles owned
 * by the internal demo domain. Visibility rules are the same ones the gallery applies.
 */
class DemoProfilesController extends Controller
{
    /** Sort key for any remaining living demo tiers (Standard Profile demos use distinguished). */
    private const TIER_ORDER = ['distinguished' => 0, 'accomplished' => 1, 'emerging' => 2];

    public function __construct(
        private PublicProfileSearchService $search,
        private PublicProfilePresentationService $presentation,
    ) {}

    public function __invoke(Request $request): View
    {
        $language = $request->query('lang') === 'ml' ? 'ml' : 'en';

        $profiles = $this->search->publishedBaseQuery()
            ->whereHas('user', fn ($query) => $query->where('email', 'like', '%'.SitemapService::DEMO_EMAIL_DOMAIN))
            ->with(['geography.district.state', 'geography.localBody', 'geography.ward', 'publicOffices', 'media', 'application', 'user'])
            ->get()
            ->sortBy(fn (Profile $profile): array => [
                self::TIER_ORDER[$profile->application?->package_tier] ?? count(self::TIER_ORDER),
                $profile->id,
            ])
            ->values();

        return view('public.demo-profiles', [
            'profiles' => $profiles,
            'presentation' => $this->presentation,
            'language' => $language,
        ]);
    }
}

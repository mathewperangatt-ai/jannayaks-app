<?php

namespace App\Http\Controllers;

use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Services\InMemoriamLifecycleService;
use App\Services\InMemoriamMediaService;
use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Review listing of the seeded demonstration records: living profiles owned
 * by, and memorials commissioner-listed under, the internal demo domain.
 * Visibility rules are the same ones the gallery and memorial pages apply.
 */
class DemoProfilesController extends Controller
{
    private const TIER_ORDER = ['emerging' => 0, 'accomplished' => 1, 'distinguished' => 2];

    public function __construct(
        private PublicProfileSearchService $search,
        private PublicProfilePresentationService $presentation,
        private InMemoriamLifecycleService $memorialLifecycle,
        private InMemoriamMediaService $memorialMedia,
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

        $memorials = InMemoriamProfile::query()
            ->where('status', InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED)
            ->whereNotNull('slug')
            ->where('commissioner_contact_email', 'like', '%'.SitemapService::DEMO_EMAIL_DOMAIN)
            ->orderBy('id')
            ->get()
            ->filter(fn (InMemoriamProfile $memorial): bool => $this->memorialLifecycle->isPubliclyVisible($memorial))
            ->map(fn (InMemoriamProfile $memorial): array => [
                'profile' => $memorial,
                'photo' => $this->memorialMedia->primaryApprovedPhotograph($memorial),
            ])
            ->values();

        return view('public.demo-profiles', [
            'profiles' => $profiles,
            'memorials' => $memorials,
            'presentation' => $this->presentation,
            'language' => $language,
        ]);
    }
}

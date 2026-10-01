<?php

namespace App\Http\Controllers;

use App\Models\InMemoriamProfile;
use App\Services\InMemoriamLifecycleService;
use App\Support\PricingAmounts;
use Illuminate\View\View;

class InMemoriamLandingController extends Controller
{
    public function __construct(private InMemoriamLifecycleService $lifecycle) {}

    public function __invoke(): View
    {
        $pricing = PricingAmounts::forInMemoriam5yr();

        // Published, publicly hosted memorial records (sombre cards).
        $memorials = InMemoriamProfile::query()
            ->where('status', InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED)
            ->whereNotNull('slug')
            ->orderByDesc('published_at')
            ->get()
            ->filter(fn (InMemoriamProfile $profile): bool => $this->lifecycle->isPubliclyVisible($profile))
            ->values();

        return view('in-memoriam.landing', [
            'pricingLabel' => ($pricing['base_formatted'] ?? null) ? $pricing['base_formatted'].' + GST' : null,
            'hostingYears' => (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 5),
            'contactEmail' => (string) config(
                'jannayaks.tier_pricing.in_memoriam.contact_email',
                config('jannayaks.contact.public_email', 'hello@jannayaks.in')
            ),
            'memorials' => $memorials,
        ]);
    }
}

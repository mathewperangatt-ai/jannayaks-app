<?php

namespace App\Http\Controllers;

use App\Support\PricingAmounts;
use Illuminate\View\View;

class FaqChargesController extends Controller
{
    public function __invoke(): View
    {
        return view('home.faq-charges', [
            'profile' => PricingAmounts::forStandardProfile(),
            'renewal' => PricingAmounts::forTierRenewal(PricingAmounts::STANDARD_INTERNAL_TIER),
            'rewrite' => PricingAmounts::forRevisionUpdate(),
            'inMemoriam' => PricingAmounts::forInMemoriam5yr(),
            'hostingYears' => (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 3),
            'gstPercent' => (float) config('jannayaks.tier_pricing.gst_percent', 18),
            'photoSlots' => (int) config('jannayaks.tier_pricing.standard_profile.photo_slots', 4),
        ]);
    }
}

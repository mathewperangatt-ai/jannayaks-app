<?php

namespace App\Http\Controllers;

use App\Support\PricingAmounts;
use Illuminate\View\View;

class FaqChargesController extends Controller
{
    public function __invoke(): View
    {
        return view('home.faq-charges', [
            'emerging' => PricingAmounts::forTier('emerging'),
            'accomplished' => PricingAmounts::forTier('accomplished'),
            'distinguished' => PricingAmounts::forTier('distinguished'),
            // Annual membership renewal is tier-priced at the applicable annual tier price.
            'renewals' => [
                'emerging' => PricingAmounts::forTierRenewal('emerging'),
                'accomplished' => PricingAmounts::forTierRenewal('accomplished'),
                'distinguished' => PricingAmounts::forTierRenewal('distinguished'),
            ],
            'revision' => PricingAmounts::forRevisionUpdate(),
            'inMemoriam' => PricingAmounts::forInMemoriam5yr(),
            'hostingYears' => (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 5),
            'gstPercent' => (float) config('jannayaks.tier_pricing.gst_percent', 18),
        ]);
    }
}

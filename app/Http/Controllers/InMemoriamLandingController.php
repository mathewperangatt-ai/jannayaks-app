<?php

namespace App\Http\Controllers;

use App\Support\PricingAmounts;
use Illuminate\View\View;

class InMemoriamLandingController extends Controller
{
    public function __invoke(): View
    {
        $pricing = PricingAmounts::forInMemoriam5yr();

        return view('in-memoriam.landing', [
            'pricingLabel' => $pricing['amount_incl_formatted'] ?? null,
            'hostingYears' => (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 5),
            'contactEmail' => (string) config(
                'jannayaks.tier_pricing.in_memoriam.contact_email',
                'hello@jannayaks.in'
            ),
        ]);
    }
}

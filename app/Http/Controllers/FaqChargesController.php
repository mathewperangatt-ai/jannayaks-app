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
        ]);
    }
}

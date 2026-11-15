<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Support\PricingAmounts;
use Tests\TestCase;

class StandardProfilePricingTest extends TestCase
{
    public function test_standard_profile_and_renewal_use_the_approved_gst_inclusive_prices(): void
    {
        // Approved final prices: ₹5,000 creation / ₹1,500 renewal, India,
        // GST-INCLUSIVE — the advertised amount is the exact checkout total.
        $profile = PricingAmounts::forStandardProfile();
        $this->assertSame('Profile', $profile['label']);
        $this->assertSame('standard_profile', $profile['tier_key']);
        $this->assertTrue($profile['gst_inclusive']);
        $this->assertSame(500000, $profile['amount_incl_paise']);
        $this->assertSame(423729, $profile['base_paise']);
        $this->assertSame(76271, $profile['gst_paise']);
        $this->assertSame('₹5,000.00', $profile['amount_incl_formatted']);
        $this->assertSame('₹4,237.29', $profile['base_formatted']);
        $this->assertSame('unclassified', $profile['gst_classification']);
        $this->assertNull($profile['cgst_formatted']);
        $this->assertNull($profile['sgst_formatted']);
        $this->assertNull($profile['igst_formatted']);
        $this->assertArrayNotHasKey('compare_at', $profile);

        $standardApp = new Application([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
        ]);
        $this->assertSame(500000, PricingAmounts::forApplication($standardApp)['amount_incl_paise']);

        foreach (PricingAmounts::livingTierKeys() as $tier) {
            $renewal = PricingAmounts::forTierRenewal($tier);
            $this->assertSame('membership_renewal', $renewal['tier_key']);
            $this->assertTrue($renewal['gst_inclusive']);
            $this->assertSame(150000, $renewal['amount_incl_paise']);
            $this->assertSame(127119, $renewal['base_paise']);
            $this->assertSame(22881, $renewal['gst_paise']);
            $this->assertSame('₹1,500.00', $renewal['amount_incl_formatted']);
            $this->assertSame('₹1,271.19', $renewal['base_formatted']);
        }
    }

    public function test_revision_has_no_published_fixed_price_and_is_quoted_individually(): void
    {
        // The revision quote remains internal-only; no customer-facing page may
        // publish it as a fixed price. Substantial rewriting is assessed and
        // quoted individually; typographical/factual corrections are free.
        $rewrite = PricingAmounts::forRevisionUpdate();
        $this->assertSame(300000, $rewrite['base_paise']);
        $this->assertFalse($rewrite['gst_inclusive']);

        $this->assertSame('Substantial profile rewriting', config('jannayaks.tier_pricing.revision.label'));
        $this->assertStringContainsString(
            'assessed individually and quoted before work begins',
            (string) config('jannayaks.tier_pricing.revision.description'),
        );
    }

    public function test_international_reference_prices_are_configured_and_display_only(): void
    {
        $this->assertSame(50, config('jannayaks.tier_pricing.international.profile_usd'));
        $this->assertSame(15, config('jannayaks.tier_pricing.international.renewal_usd'));

        // The gateway stays INR-only: no USD quote path exists.
        $this->assertSame('INR', PricingAmounts::forStandardProfile()['currency']);
    }

    public function test_historical_package_amounts_match_pr_base_f8eef32_and_are_not_silently_repriced(): void
    {
        // Historical living tiers from PR base commit f8eef3295a405c5d872a163e678bf9c1d0566d7f.
        $this->assertSame(300000, PricingAmounts::forTier('emerging')['base_paise']);
        $this->assertSame(354000, PricingAmounts::forTier('emerging')['amount_incl_paise']);
        $this->assertSame(600000, PricingAmounts::forTier('accomplished')['base_paise']);
        $this->assertSame(708000, PricingAmounts::forTier('accomplished')['amount_incl_paise']);
        $this->assertSame(900000, PricingAmounts::forTier('distinguished')['base_paise']);
        $this->assertSame(1062000, PricingAmounts::forTier('distinguished')['amount_incl_paise']);

        foreach (['emerging' => 354000, 'accomplished' => 708000, 'distinguished' => 1062000] as $tier => $total) {
            $historical = new Application([
                'package_tier' => $tier,
                'pricing_generation' => null,
            ]);
            $this->assertSame($total, PricingAmounts::forApplication($historical)['amount_incl_paise'], $tier);
            $this->assertFalse(PricingAmounts::forApplication($historical)['gst_inclusive'], $tier);
        }

        $this->assertNotSame(
            PricingAmounts::forStandardProfile()['amount_incl_paise'],
            PricingAmounts::forApplication(new Application([
                'package_tier' => 'distinguished',
                'pricing_generation' => null,
            ]))['amount_incl_paise'],
        );
    }

}

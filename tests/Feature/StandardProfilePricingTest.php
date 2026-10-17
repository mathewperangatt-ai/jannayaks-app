<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Support\PricingAmounts;
use Tests\TestCase;

class StandardProfilePricingTest extends TestCase
{
    public function test_standard_profile_renewal_and_rewrite_use_the_approved_exclusive_prices(): void
    {
        $profile = PricingAmounts::forStandardProfile();
        $this->assertSame('Standard Profile', $profile['label']);
        $this->assertSame('standard_profile', $profile['tier_key']);
        $this->assertSame(400000, $profile['base_paise']);
        $this->assertSame(72000, $profile['gst_paise']);
        $this->assertSame(472000, $profile['amount_incl_paise']);
        $this->assertSame('₹4,000.00', $profile['base_formatted']);
        $this->assertSame('₹4,720.00', $profile['amount_incl_formatted']);
        $this->assertSame('unclassified', $profile['gst_classification']);
        $this->assertNull($profile['cgst_formatted']);
        $this->assertNull($profile['sgst_formatted']);
        $this->assertNull($profile['igst_formatted']);
        $this->assertFalse($profile['gst_inclusive']);
        $this->assertArrayNotHasKey('compare_at', $profile);

        $standardApp = new Application([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
        ]);
        $this->assertSame(472000, PricingAmounts::forApplication($standardApp)['amount_incl_paise']);

        foreach (PricingAmounts::livingTierKeys() as $tier) {
            $renewal = PricingAmounts::forTierRenewal($tier);
            $this->assertSame('membership_renewal', $renewal['tier_key']);
            $this->assertSame(180000, $renewal['base_paise']);
            $this->assertSame(32400, $renewal['gst_paise']);
            $this->assertSame(212400, $renewal['amount_incl_paise']);
            $this->assertSame('₹1,800.00', $renewal['base_formatted']);
            $this->assertFalse($renewal['gst_inclusive']);
        }

        $rewrite = PricingAmounts::forRevisionUpdate();
        $this->assertSame(300000, $rewrite['base_paise']);
        $this->assertSame(54000, $rewrite['gst_paise']);
        $this->assertSame(354000, $rewrite['amount_incl_paise']);
        $this->assertSame('₹3,000.00', $rewrite['base_formatted']);
        $this->assertFalse($rewrite['gst_inclusive']);
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
        }

        $standard = new Application([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
        ]);
        $this->assertSame(472000, PricingAmounts::forApplication($standard)['amount_incl_paise']);
        $this->assertNotSame(
            PricingAmounts::forStandardProfile()['amount_incl_paise'],
            PricingAmounts::forApplication(new Application([
                'package_tier' => 'distinguished',
                'pricing_generation' => null,
            ]))['amount_incl_paise'],
        );
    }

    public function test_in_memoriam_price_is_unchanged(): void
    {
        $memorial = PricingAmounts::forInMemoriam5yr();

        $this->assertSame(2400000, $memorial['base_paise']);
        $this->assertFalse($memorial['gst_inclusive']);
    }
}

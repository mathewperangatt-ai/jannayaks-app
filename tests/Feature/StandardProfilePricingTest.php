<?php

namespace Tests\Feature;

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
        $this->assertFalse($profile['gst_inclusive']);
        $this->assertArrayNotHasKey('compare_at', $profile);

        foreach (PricingAmounts::livingTierKeys() as $tier) {
            $quote = PricingAmounts::forTier($tier);
            $this->assertSame($profile['amount_incl_paise'], $quote['amount_incl_paise']);
            $this->assertSame(
                $profile['amount_incl_paise'],
                PricingAmounts::forApplicationPackage($tier)['amount_incl_paise']
            );

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

    public function test_in_memoriam_price_is_unchanged(): void
    {
        $memorial = PricingAmounts::forInMemoriam5yr();

        $this->assertSame(2400000, $memorial['base_paise']);
        $this->assertFalse($memorial['gst_inclusive']);
    }
}

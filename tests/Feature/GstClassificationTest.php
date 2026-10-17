<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Support\PricingAmounts;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GstClassificationTest extends TestCase
{
    public function test_intra_state_uses_cgst_and_sgst_when_seller_and_place_match(): void
    {
        Config::set('jannayaks.tier_pricing.billing.state', 'Kerala');

        $quote = PricingAmounts::forStandardProfile('Keralam');
        $this->assertSame('intra_state', $quote['gst_classification']);
        $this->assertSame(72000, $quote['gst_paise']);
        $this->assertSame(36000, $quote['cgst_paise']);
        $this->assertSame(36000, $quote['sgst_paise']);
        $this->assertNull($quote['igst_paise']);
        $this->assertSame(472000, $quote['amount_incl_paise']);
        $this->assertSame(
            $quote['amount_incl_paise'],
            $quote['base_paise'] + $quote['cgst_paise'] + $quote['sgst_paise'],
        );
    }

    public function test_inter_state_uses_igst_when_seller_and_place_differ(): void
    {
        Config::set('jannayaks.tier_pricing.billing.state', 'Kerala');

        $quote = PricingAmounts::forStandardProfile('Maharashtra');
        $this->assertSame('inter_state', $quote['gst_classification']);
        $this->assertSame(72000, $quote['gst_paise']);
        $this->assertSame(72000, $quote['igst_paise']);
        $this->assertNull($quote['cgst_paise']);
        $this->assertNull($quote['sgst_paise']);
        $this->assertSame(472000, $quote['amount_incl_paise']);
        $this->assertSame($quote['amount_incl_paise'], $quote['base_paise'] + $quote['igst_paise']);
    }

    public function test_missing_seller_or_place_does_not_invent_a_split(): void
    {
        Config::set('jannayaks.tier_pricing.billing.state', '');
        $noSeller = PricingAmounts::forStandardProfile('Kerala');
        $this->assertSame('unclassified', $noSeller['gst_classification']);
        $this->assertNull($noSeller['cgst_paise']);
        $this->assertNull($noSeller['igst_paise']);
        $this->assertSame(472000, $noSeller['amount_incl_paise']);

        Config::set('jannayaks.tier_pricing.billing.state', 'Kerala');
        $noPlace = PricingAmounts::forApplication(new Application([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
        ]));
        $this->assertSame('unclassified', $noPlace['gst_classification']);
        $this->assertNull($noPlace['cgst_paise']);
        $this->assertNull($noPlace['igst_paise']);
        $this->assertSame(472000, $noPlace['amount_incl_paise']);
    }

    public function test_historical_tiers_preserve_totals_under_each_classification(): void
    {
        Config::set('jannayaks.tier_pricing.billing.state', 'Kerala');

        $emerging = PricingAmounts::forTier('emerging', 'Kerala');
        $this->assertSame(354000, $emerging['amount_incl_paise']);
        $this->assertSame('intra_state', $emerging['gst_classification']);

        $accomplished = PricingAmounts::forTier('accomplished', 'Tamil Nadu');
        $this->assertSame(708000, $accomplished['amount_incl_paise']);
        $this->assertSame('inter_state', $accomplished['gst_classification']);
        $this->assertSame(108000, $accomplished['igst_paise']);

        $distinguished = PricingAmounts::forTier('distinguished', 'Kerala');
        $this->assertSame(1062000, $distinguished['amount_incl_paise']);
        $this->assertSame(81000, $distinguished['cgst_paise']);
        $this->assertSame(81000, $distinguished['sgst_paise']);
    }
}

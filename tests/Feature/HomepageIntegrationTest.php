<?php

namespace Tests\Feature;

use App\Support\PricingAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_route_renders_integrated_homepage(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('noindex, nofollow', false);
        $response->assertSee('Under Construction', false);
        $response->assertSee('branding/favicon-32.png', false);
        $response->assertSee('How It Works', false);
        $response->assertSee('FAQ &amp; Charges', false);
        $response->assertSee('View Demo Profiles', false);
        $response->assertSee('Create Profile', false);
        $response->assertSee('Sign In', false);
        $response->assertSee('In Memoriam', false);
        $response->assertSee('Editorial Preparation', false);
        $response->assertSee('View Demo Profiles →', false);
        $response->assertSee('FAQ &amp; Charges →', false);
        $response->assertSee('nav-menu-btn', false);
        $response->assertSee('Open menu', false);
        $response->assertSee('branding/jannayaks-logo.jpg', false);
        $response->assertSee('f-logo', false);
        $response->assertDontSee('brightness(0) invert(1)', false);
        $response->assertDontSee('Well-wishers', false);
        $response->assertDontSee('🙏', false);
        $response->assertDontSee('₹25,000 / 3 years', false);
        $response->assertDontSee('Three tiers. One platform.', false);
        $response->assertDontSee('goes live in both languages — permanently', false);
        $response->assertDontSee('Apolitical. Verified. Permanent.', false);
        $response->assertDontSee('href="#tiers"', false);
        $response->assertSee(route('gallery.index', absolute: false), false);
        $response->assertSee(route('faq-charges', absolute: false), false);
        $response->assertSee(route('login', absolute: false), false);
        $response->assertSee(route('apply', absolute: false), false);
        $response->assertSee(route('in-memoriam.index', absolute: false), false);
        $response->assertSee(route('search.index', absolute: false), false);
    }

    public function test_faq_charges_uses_authoritative_pricing(): void
    {
        $emerging = PricingAmounts::forTier('emerging');
        $accomplished = PricingAmounts::forTier('accomplished');
        $distinguished = PricingAmounts::forTier('distinguished');
        $inMemoriam = PricingAmounts::forInMemoriam5yr();

        $response = $this->get(route('faq-charges'));

        $response->assertOk();
        $response->assertSee('noindex, nofollow', false);
        $response->assertSee('Under Construction', false);
        $response->assertSee($emerging['amount_incl_formatted'], false);
        $response->assertSee($accomplished['amount_incl_formatted'], false);
        $response->assertSee($distinguished['amount_incl_formatted'], false);
        $response->assertSee($inMemoriam['amount_incl_formatted'], false);
        $response->assertSee('3 years', false);
        $response->assertDontSee('₹29,500', false);
        $response->assertSee(route('in-memoriam.index', absolute: false), false);
        $response->assertSee(route('gallery.index', absolute: false), false);
    }

    public function test_existing_key_routes_still_respond(): void
    {
        $this->get(route('gallery.index'))->assertOk();
        $this->get(route('search.index'))->assertOk();
        $this->get(route('in-memoriam.index'))
            ->assertOk()
            ->assertSee('₹25,000', false)
            ->assertSee('3 years', false)
            ->assertDontSee('₹29,500', false);
        $this->get(route('login'))->assertOk();
        $this->get(route('apply'))->assertOk();
    }
}

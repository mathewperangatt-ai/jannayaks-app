<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 approved pricing presentation:
 * - ₹5,000 creation / ₹1,500 renewal (India, GST-inclusive), US$50/US$15 international reference.
 * - No fixed full-profile-rewrite price: substantial rewriting is assessed and quoted individually.
 * - Typographical and factual corrections are free; minor updates stay in the customer panel.
 * - No crossed-out prices, no premium/founding tiers.
 */
class Phase2PricingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_charges_states_inclusive_prices_free_corrections_and_individual_quotes(): void
    {
        $response = $this->get(route('faq-charges'))->assertOk();

        $response->assertSee('GST-inclusive', false);
        $response->assertSee('₹5,000.00', false);
        $response->assertSee('₹1,500.00', false);
        $response->assertDontSee('₹3,000', false);
        $response->assertDontSee('full profile rewrite costs', false);
        $response->assertDontSee('priced at', false);
        $response->assertSee('Typographical and factual corrections are free', false);
        $response->assertSee('assessed individually', false);
        $response->assertSee('quotes the work before it begins', false);
    }

    public function test_tier_select_shows_the_inclusive_amounts(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('apply'))
            ->assertOk()
            ->assertSee('₹5,000.00', false)
            ->assertSee('₹1,500.00', false)
            ->assertSee('(India, GST-inclusive)', false)
            ->assertDontSee('+ GST', false);
    }

    public function test_maintenance_request_states_free_corrections_and_no_fixed_rewrite_price(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = \App\Models\Profile::query()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'full_name' => 'Maintenance Price Member',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = \App\Models\Application::factory()->paid()->create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'status' => \App\Models\Application::STATUS_PUBLISHED,
        ]);

        // Render the view directly: the wording, not the controller gates, is under test.
        // The non-eligible branch is where a paid rewrite used to be priced.
        $this->view('application.maintenance-request', [
            'application' => $application,
            'member' => $user,
            'profile' => $profile,
            'eligibility' => [
                'eligible' => false,
                'next_eligible_on' => \Illuminate\Support\Carbon::parse('+1 month'),
            ],
            'openRequest' => null,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])
            ->assertDontSee('+ GST', false)
            ->assertDontSee('₹3,000', false)
            ->assertSee('Typographical and factual corrections are always free', false)
            ->assertSee('will assess it individually, quote the work, and', false);
    }

    public function test_no_premium_or_founding_tiers_anywhere_on_public_pricing(): void
    {
        $home = $this->get(route('home'))->assertOk();
        $faq = $this->get(route('faq-charges'))->assertOk();

        foreach ([$home, $faq] as $response) {
            $response->assertDontSee('Founding', false);
            $response->assertDontSee('Premium', false);
            $response->assertDontSee('compare at', false);
            $response->assertDontSee('<s>', false);
            $response->assertDontSee('<strike', false);
            $response->assertDontSee('text-decoration:line-through', false);
        }
    }
}

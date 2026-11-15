<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Support\Countries;
use App\Support\PricingAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3: structured country of residence (ISO 3166-1 alpha-2). It is
 * captured at intake, determines the pricing category, is correctable by
 * administrators with audit information, is never inferred from IP address,
 * telephone country code or browser locale, and is never shown on a
 * public profile.
 */
class CountryOfResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_selector_offers_india_and_other_countries(): void
    {
        $countries = Countries::all();
        $this->assertSame('IN', Countries::INDIA);
        $this->assertArrayHasKey('IN', $countries);
        $this->assertArrayHasKey('US', $countries);
        $this->assertArrayHasKey('AE', $countries);
        $this->assertArrayHasKey('GB', $countries);
        foreach ($countries as $code => $name) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code, $code.' must be ISO 3166-1 alpha-2.');
        }
    }

    public function test_intake_requires_a_valid_country_of_residence(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)->post(route('apply.intent', ['lang' => 'en']), [
            'source_method' => 'online_interview',
            'full_name' => 'Country Test Member',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'country.test@example.com',
            'solicitation_declaration' => '1',
        ])->assertSessionHasErrors(['country_of_residence' => 'Please select your country of residence. It determines the applicable price.']);
        $this->assertDatabaseCount('applications', 0);

        $this->actingAs($member)->post(route('apply.intent', ['lang' => 'en']), [
            'source_method' => 'online_interview',
            'full_name' => 'Country Test Member',
            'broad_location' => 'Kozhikode',
            'country_of_residence' => 'XX',
            'contact_email' => 'country.test@example.com',
            'solicitation_declaration' => '1',
        ])->assertSessionHasErrors(['country_of_residence' => 'Please select a valid country of residence from the list.']);
        $this->assertDatabaseCount('applications', 0);

        $this->actingAs($member)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Country Test Member',
            'broad_location' => 'Kozhikode',
            'country_of_residence' => 'us',
            'contact_email' => 'country.test@example.com',
            'solicitation_declaration' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $application = Application::query()->latest('id')->firstOrFail();
        $this->assertSame('US', $application->country_of_residence, 'Country stored as normalised uppercase ISO code.');
    }

    public function test_guest_country_survives_sign_in_and_is_stored(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);

        $this->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Guest Country Member',
            'broad_location' => 'Kozhikode',
            'country_of_residence' => 'AE',
            'contact_email' => 'guest.country@example.com',
            'solicitation_declaration' => '1',
        ]);

        $this->actingAs($guest)->get(route('apply.continue'))->assertRedirect();

        $application = Application::query()->latest('id')->firstOrFail();
        $this->assertSame('AE', $application->country_of_residence, 'Guest acceptance country survives sign-in.');
    }

    public function test_administrator_can_correct_the_country_with_audit_information(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create(['country_of_residence' => 'IN']);

        Livewire::actingAs($admin)->test(\App\Filament\Resources\Applications\Pages\ViewApplication::class, [
            'record' => $application->getRouteKey(),
        ])->callAction('correctCountryOfResidence', data: [
            'country_of_residence' => 'US',
        ]);

        $this->assertSame('US', $application->fresh()->country_of_residence);
        $this->assertTrue(StaffActionLog::query()
            ->where('action', 'application.country_of_residence_corrected')
            ->where('subject_id', $application->id)
            ->exists(), 'The correction is on the audit record.');
    }

    public function test_member_cannot_open_the_admin_application_page(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create(['country_of_residence' => 'IN']);

        Livewire::actingAs($member)->test(\App\Filament\Resources\Applications\Pages\ViewApplication::class, [
            'record' => $application->getRouteKey(),
        ])->assertForbidden();

        $this->assertSame('IN', $application->fresh()->country_of_residence);
    }

    public function test_country_of_residence_is_never_shown_on_the_public_profile(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Country Privacy Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $english = \App\Models\EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => \App\Models\EditorialContent::LANGUAGE_EN,
            'version_number' => 1,
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $admin->id,
            'title' => 'Country Privacy Subject',
            'summary' => 'Summary.',
            'body' => 'Biography body.',
        ]);

        $application = Application::factory()->paid()->for($member)->create([
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'country_of_residence' => 'AE',
        ]);

        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);
        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $application->fresh()->preview_english_editorial_content_id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);

        $profile = $profile->fresh();
        $this->assertNotNull($profile->slug);
        $page = $this->get('/'.$profile->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('United Arab Emirates', $page);
        $this->assertStringNotContainsString('AE)', $page, 'No raw country code suffix is rendered publicly.');
    }

    public function test_country_is_not_accepted_through_payment_manipulation(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        // Stored as Indian resident at intake; unpaid so a payment link can start.
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'IN',
        ]);
        Config::set('services.razorpay.enabled', false);

        $this->assertSame(500000, PricingAmounts::forApplication($application->fresh())['amount_incl_paise']);
        $this->assertSame('INR', PricingAmounts::forApplication($application->fresh())['currency']);

        // A country hint posted at payment time must not reprice anything:
        // the server derives the category from the stored application value.
        $this->actingAs($member)
            ->postJson(route('applications.payment.initiate', $application), [
                'country_of_residence' => 'US',
            ])
            ->assertStatus(201);

        $payment = Payment::query()->where('application_id', $application->id)->latest('id')->firstOrFail();
        $this->assertSame('INR', $payment->currency, 'Stored country stays authoritative: no USD substitution at checkout.');
    }
}

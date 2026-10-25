<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\GeoDistrict;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileLocationIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_kerala_location_is_collected_once_and_can_be_searched(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $district = GeoDistrict::query()->where('name', 'Thiruvananthapuram')->firstOrFail();

        $this->actingAs($user)->get(route('apply', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Broad location', false)
            ->assertSee('District', false)
            ->assertSee('Somewhere else', false)
            ->assertDontSee('name="local_body_id"', false)
            ->assertDontSee('name="ward_id"', false)
            ->assertDontSee('name="panchayat"', false);

        $this->get('/search')
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('name="district_id"', false)
            ->assertSee('name="state"', false)
            ->assertSee('name="country"', false)
            ->assertSee('>Search<', false);

        $created = $this->actingAs($user)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Anjali Nair',
            'contact_email' => 'anjali.private@example.test',
            'preferred_slug' => '',
            'location_scope' => 'kerala',
            'district_id' => $district->id,
        ]);
        $created->assertRedirect();
        $this->assertStringContainsString('/interview', $created->headers->get('Location'));

        $application = Application::query()->where('user_id', $user->id)->sole();
        $this->assertSame('kerala', $application->location_scope);
        $this->assertSame($district->id, $application->location_district_id);
        $this->assertSame('Keralam', $application->location_state);
        $this->assertSame('India', $application->location_country);
        $this->assertNull($application->broad_location);
        $this->assertSame(0, Payment::query()->count());

        $this->actingAs($user)->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertDontSee('name="district_id"', false)
            ->assertDontSee('name="location_scope"', false);

        $this->actingAs($user)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Anjali Nair',
            'contact_email' => 'anjali.private@example.test',
            'location_scope' => 'kerala',
            'district_id' => $district->id,
        ])->assertRedirect(route('applications.show', $application));
        $this->assertSame(1, Application::query()->count());

        $profile = app(ProfileUrlService::class)->ensureDraftProfileForApplication($application->fresh());
        $geography = $profile->geography()->with('district')->firstOrFail();
        $this->assertSame($district->id, $geography->district_id);
        $this->assertNull($geography->local_body_id);
        $this->assertNull($geography->ward_id);
        $this->assertNull($geography->locality_place);
        $this->assertSame('Keralam', $geography->state_region_name);
        $this->assertSame('IN', $geography->country_code);
        $this->assertSame('India', $geography->country_name);

        $profile->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'slug' => 'anjali.nair',
            'profession' => 'Doctor',
            'full_name' => 'Anjali Nair',
            'display_name' => 'Anjali Nair',
        ])->save();

        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Secret Unpublished',
            'display_name' => 'Secret Unpublished',
            'profession' => 'Doctor',
            'slug' => 'secret.unpublished',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->get('/search?q=Doctor')
            ->assertOk()
            ->assertSee('Anjali Nair', false)
            ->assertSee('Broad location', false)
            ->assertSee('Thiruvananthapuram', false)
            ->assertDontSee('Secret Unpublished', false)
            ->assertDontSee('anjali.private@example.test', false);

        $this->get('/search?q=Anjali')->assertOk()->assertSee('Anjali Nair', false);
        $this->get('/search?q=Thiruvananthapuram')->assertOk()->assertSee('Anjali Nair', false);
        $this->get('/search?q='.urlencode('anjali.private@example.test'))
            ->assertOk()
            ->assertDontSee('Anjali Nair', false);
        $this->get('/search?district_id='.$district->id)->assertOk()->assertSee('Anjali Nair', false);
        $this->get('/search?state=Keralam')->assertOk()->assertSee('Anjali Nair', false);
        $this->get('/search?country=India')->assertOk()->assertSee('Anjali Nair', false);

        $this->get(route('profiles.public', 'anjali.nair'))
            ->assertOk()
            ->assertSee('Broad location', false)
            ->assertSee('Thiruvananthapuram, Keralam', false)
            ->assertSee('KERALAM · INDIA', false)
            ->assertDontSee('anjali.private@example.test', false);
    }

    public function test_locations_outside_kerala_stay_free_text_and_remain_filterable(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->from(route('apply', ['lang' => 'en']))
            ->post(route('apply.intent'), [
                'source_method' => 'online_interview',
                'full_name' => 'Leah George',
                'contact_email' => 'leah.george@example.test',
                'location_scope' => 'elsewhere',
            ])
            ->assertSessionHasErrors(['broad_location', 'location_state', 'location_country']);
        $this->assertSame(0, Application::query()->count());

        $created = $this->actingAs($user)->post(route('apply.intent'), [
            'source_method' => 'direct_submission',
            'full_name' => 'Leah George',
            'contact_email' => 'leah.george@example.test',
            'location_scope' => 'elsewhere',
            'broad_location' => 'London',
            'location_state' => 'England',
            'location_country' => 'United Kingdom',
        ]);
        $created->assertRedirect();
        $this->assertStringContainsString('/upload', (string) $created->headers->get('Location'));

        $application = Application::query()->where('user_id', $user->id)->sole();
        $this->assertSame('elsewhere', $application->location_scope);
        $this->assertNull($application->location_district_id);
        $this->assertSame('London', $application->broad_location);
        $this->assertSame('England', $application->location_state);
        $this->assertSame('United Kingdom', $application->location_country);
        $this->assertSame(Application::STATUS_INTAKE_IN_PROGRESS, $application->status);

        $profile = app(ProfileUrlService::class)->ensureDraftProfileForApplication($application->fresh());
        $geography = $profile->geography()->firstOrFail();
        $this->assertNull($geography->district_id);
        $this->assertNull($geography->local_body_id);
        $this->assertSame('London', $geography->locality_place);
        $this->assertSame('England', $geography->state_region_name);
        $this->assertSame('United Kingdom', $geography->country_name);
        $this->assertSame('ZZ', $geography->country_code);

        $profile->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'slug' => 'leah.george',
            'profession' => 'Writer',
            'full_name' => 'Leah George',
            'display_name' => 'Leah George',
        ])->save();

        $keralaDistrict = GeoDistrict::query()->where('name', 'Kollam')->firstOrFail();

        $this->get('/search?q=London')
            ->assertOk()
            ->assertSee('Broad location', false)
            ->assertSee('London, England, United Kingdom', false);
        $this->get('/search?q=England')->assertOk()->assertSee('Leah George', false);
        $this->get('/search?state=England')->assertOk()->assertSee('Leah George', false);
        $this->get('/search?country='.urlencode('United Kingdom'))->assertOk()->assertSee('Leah George', false);
        $this->get('/search?district_id='.$keralaDistrict->id)
            ->assertOk()
            ->assertDontSee('Leah George', false);

        $this->get(route('profiles.public', 'leah.george'))
            ->assertOk()
            ->assertSee('Broad location', false)
            ->assertSee('BROAD LOCATION', false)
            ->assertSee('London, England, United Kingdom', false)
            ->assertDontSee('KERALAM · INDIA', false);
    }

    public function test_kerala_district_is_required_when_that_scope_is_chosen(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('apply', ['lang' => 'en']))->assertOk();

        $this->actingAs($user)->from(route('apply', ['lang' => 'en']))
            ->post(route('apply.intent'), [
                'source_method' => 'online_interview',
                'full_name' => 'Anjali Nair',
                'contact_email' => 'anjali.private@example.test',
                'location_scope' => 'kerala',
            ])
            ->assertSessionHasErrors(['district_id' => 'Choose the district in Keralam.']);

        $this->assertSame(0, Application::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }
}

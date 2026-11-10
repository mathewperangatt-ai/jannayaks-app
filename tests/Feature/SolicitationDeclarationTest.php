<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Professional and business solicitation declaration on the application form:
 * a separate, mandatory acceptance, recorded as consent evidence with the
 * declaration version and timestamp.
 */
class SolicitationDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private const EN_WORDING = 'I understand that Jannayaks is a digital gallery for documenting personal and professional achievements, not a platform for advertising professional services, soliciting clients or promoting businesses.';

    public function test_form_shows_the_declaration_in_the_selected_language(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)->get(route('apply', ['lang' => 'en']))
            ->assertOk()
            ->assertSee(self::EN_WORDING, false)
            ->assertSee('name="solicitation_declaration"', false);

        $this->actingAs($member)->get(route('apply', ['lang' => 'ml']))
            ->assertOk()
            ->assertSee('പ്രൊഫഷണൽ സേവനങ്ങളുടെ പരസ്യത്തിനോ ഉപഭോക്താക്കളെ ആകർഷിക്കുന്നതിനോ ബിസിനസ് പ്രചാരണത്തിനോ ഉള്ള വേദിയല്ലെന്നും', false)
            ->assertSee('name="solicitation_declaration"', false);
    }

    public function test_intent_is_refused_without_the_declaration(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $payload = [
            'source_method' => 'online_interview',
            'full_name' => 'No Declaration',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'no.declaration@example.com',
        ];

        $this->actingAs($member)
            ->post(route('apply.intent', ['lang' => 'en']), $payload)
            ->assertSessionHasErrors([
                'solicitation_declaration' => 'Please confirm the declaration about professional and business solicitation before continuing.',
            ]);
        $this->assertDatabaseCount('applications', 0);

        $this->actingAs($member)
            ->post(route('apply.intent', ['lang' => 'ml']), $payload)
            ->assertSessionHasErrors([
                'solicitation_declaration' => 'ദയവായി പ്രൊഫഷണൽ, ബിസിനസ് പ്രചാരണ പ്രഖ്യാപനം സമ്മതിച്ചതിന് ശേഷം തുടരുക.',
            ]);
        $this->assertDatabaseCount('applications', 0);

        // Unchecked boxes post no value at all; an explicit unchecked value is refused too.
        $this->actingAs($member)
            ->post(route('apply.intent', ['lang' => 'en']), $payload + ['solicitation_declaration' => '0'])
            ->assertSessionHasErrors('solicitation_declaration');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_guest_intent_also_requires_the_declaration(): void
    {
        $this->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Guest Without Declaration',
            'broad_location' => 'Kochi',
            'contact_email' => 'guest.nodecl@example.com',
        ])->assertSessionHasErrors('solicitation_declaration');
        $this->assertFalse(session()->has('apply.intent'));

        $this->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Guest With Declaration',
            'broad_location' => 'Kochi',
            'contact_email' => 'guest.with@example.com',
            'solicitation_declaration' => '1',
        ])->assertRedirect();
        $this->assertTrue(session()->has('apply.intent'), 'The accepted guest intent is stored for the sign-in continuation.');
    }

    public function test_accepted_declaration_is_recorded_as_consent_evidence(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Declared Applicant',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'declared@example.com',
            'solicitation_declaration' => '1',
        ])->assertRedirect();

        $application = Application::query()->where('user_id', $member->id)->sole();

        $consent = ConsentRecord::query()
            ->where('user_id', $member->id)
            ->where('consent_key', 'application.solicitation_declaration')
            ->sole();
        $this->assertTrue($consent->consented);
        $this->assertSame('v1', $consent->notice_version);
        $this->assertNotNull($consent->action_at);
        $this->assertSame($application->id, (int) $consent->application_id);
    }

    public function test_repeat_acceptance_does_not_duplicate_consent_records(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'source_method' => 'online_interview',
            'package_tier' => 'distinguished',
        ]);

        // A second application records its own declaration acceptance without
        // duplicating the unrelated general consents.
        $consentsBefore = ConsentRecord::query()->count();

        $this->actingAs($member)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Second Start',
            'broad_location' => 'Kochi',
            'contact_email' => 'second@example.com',
            'solicitation_declaration' => '1',
        ]);

        $this->assertSame($consentsBefore, ConsentRecord::query()->count());
    }
}

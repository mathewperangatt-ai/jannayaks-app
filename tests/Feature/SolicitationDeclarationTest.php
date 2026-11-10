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
            ->where('consent_key', 'application.solicitation_declaration:'.$application->id)
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

    public function test_direct_creation_requires_the_declaration(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $payload = [
            'source_method' => 'online_interview',
            'full_name' => 'Direct Route Applicant',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'direct@example.com',
        ];

        $this->actingAs($member)
            ->postJson(route('applications.store'), $payload)
            ->assertJsonValidationErrors('solicitation_declaration');

        $this->assertSame(0, Application::query()->where('user_id', $member->id)->count());
        $this->assertSame(0, ConsentRecord::query()->count(), 'No consent evidence for a refused creation.');
    }

    public function test_direct_creation_with_the_declaration_records_linked_evidence(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)
            ->postJson(route('applications.store'), [
                'source_method' => 'online_interview',
                'full_name' => 'Direct Route Applicant',
                'broad_location' => 'Kozhikode',
                'contact_email' => 'direct@example.com',
                'solicitation_declaration' => '1',
            ])
            ->assertCreated();

        $application = Application::query()->where('user_id', $member->id)->sole();
        $consent = ConsentRecord::query()
            ->where('user_id', $member->id)
            ->where('consent_key', 'application.solicitation_declaration:'.$application->id)
            ->sole();
        $this->assertSame('v1', $consent->notice_version);
        $this->assertNotNull($consent->action_at);
        $this->assertSame($application->id, (int) $consent->application_id);
    }

    public function test_guest_acceptance_survives_sign_in_and_is_recorded(): void
    {
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'password' => 'correct-horse',
            'username' => 'guest.survivor',
        ]);

        // Guest accepts the declaration; the intent is stored for sign-in.
        $this->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Guest Applicant',
            'broad_location' => 'Kochi',
            'contact_email' => 'guest@example.com',
            'solicitation_declaration' => '1',
        ])->assertRedirect();
        $this->assertTrue(session()->has('apply.intent'));
        $this->assertSame(0, ConsentRecord::query()->count(), 'No user account yet — nothing recorded.');

        // Sign-in continues the intent: the application and its consent evidence
        // are created for the signed-in account.
        $this->post(route('login.attempt'), [
            'username' => 'guest.survivor',
            'password' => 'correct-horse',
        ])->assertRedirect();

        $application = Application::query()->where('user_id', $member->id)->sole();
        $consent = ConsentRecord::query()
            ->where('user_id', $member->id)
            ->where('consent_key', 'application.solicitation_declaration:'.$application->id)
            ->sole();
        $this->assertTrue($consent->consented);
        $this->assertSame('v1', $consent->notice_version);
        $this->assertNotNull($consent->action_at);
    }

    public function test_fresh_application_after_expiry_gets_its_own_linked_evidence(): void
    {
        $member = User::factory()->create(['email_verified_at' => now(), 'password' => 'correct-horse', 'username' => 'fresh.after.expiry']);

        // First application: created through the form with the declaration
        // accepted, then left unsubmitted past Day 10 and erased on Day 11.
        $this->actingAs($member)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Expired Applicant',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'expired@example.com',
            'solicitation_declaration' => '1',
        ])->assertRedirect();
        $expired = Application::query()->where('user_id', $member->id)->sole();
        $expired->forceFill([
            'online_interview_started_at' => '2026-11-01 04:30:00',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ])->save();

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-11', '--execute' => true])
            ->assertExitCode(0);

        // The declaration evidence is retained, detached from the erased application.
        $expiredConsent = ConsentRecord::query()
            ->where('user_id', $member->id)
            ->where('consent_key', 'application.solicitation_declaration:'.$expired->id)
            ->sole();
        $this->assertNull($expiredConsent->application_id);

        // The retained account starts a fresh application; its acceptance is
        // recorded as new, accurately linked evidence.
        $this->actingAs($member)->post(route('apply.intent'), [
            'source_method' => 'online_interview',
            'full_name' => 'Fresh Start',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'fresh@example.com',
            'solicitation_declaration' => '1',
        ])->assertRedirect();

        $fresh = Application::query()->where('user_id', $member->id)->latest('id')->first();
        $freshConsent = ConsentRecord::query()
            ->where('user_id', $member->id)
            ->where('consent_key', 'application.solicitation_declaration:'.$fresh->id)
            ->sole();
        $this->assertSame($fresh->id, (int) $freshConsent->application_id);
        $this->assertSame('v1', $freshConsent->notice_version);
        $this->assertNotNull($freshConsent->action_at);
        $this->assertNotSame($expiredConsent->id, $freshConsent->id);
    }
}

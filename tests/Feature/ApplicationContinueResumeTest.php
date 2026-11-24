<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wave 3 follow-up bug: an applicant clicking Continue with their OWN
 * existing application was bounced back to the beginning of the intake flow
 * with a duplicate-email warning. The browser (non-JSON) path now resumes
 * the applicant's own application; cross-account email collisions stay
 * blocked, and the JSON 409 contract is unchanged.
 */
class ApplicationContinueResumeTest extends TestCase
{
    use RefreshDatabase;

    private function storePayload(string $email, string $name = 'Resume Fixture'): array
    {
        return [
            'country_of_residence' => 'IN',
            'solicitation_declaration' => '1',
            'source_method' => 'online_interview',
            'full_name' => $name,
            'broad_location' => 'Kozhikode',
            'contact_email' => $email,
        ];
    }

    /** Own existing application: the browser path resumes it, not the beginning. */
    public function test_own_application_is_resumed_not_blocked(): void
    {
        $member = User::factory()->create(['email' => 'resume.person@example.com', 'role' => User::ROLE_MEMBER]);
        $existing = Application::factory()->create([
            'user_id' => $member->id,
            'applicant_email_normalized' => 'resume.person@example.com',
            'preferred_contact_email' => 'resume.person@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
            'full_name' => 'Resume Person',
        ]);

        // Plain (non-JSON) form post, exactly as the Continue button sends it.
        $response = $this->actingAs($member)->post(route('applications.store'), $this->storePayload('resume.person@example.com'));

        $response->assertRedirect(route('online-interview.show', ['application' => $existing->id]));
        $this->assertNull(session('errors'), 'No duplicate-email warning for the applicant’s own application.');

        // No duplicate row was created.
        $this->assertSame(1, Application::query()->where('user_id', $member->id)->count());
    }

    /** Cross-account email collisions remain blocked on the browser path. */
    public function test_cross_account_email_still_blocked_with_duplicate_notice(): void
    {
        $owner = User::factory()->create(['email' => 'owner.person@example.com', 'role' => User::ROLE_MEMBER]);
        Application::factory()->create([
            'user_id' => $owner->id,
            'applicant_email_normalized' => 'owner.person@example.com',
            'status' => Application::STATUS_PUBLISHED,
            'full_name' => 'Owner Person',
        ]);

        $stranger = User::factory()->create(['email' => 'stranger.person@example.com', 'role' => User::ROLE_MEMBER]);

        $response = $this->actingAs($stranger)->post(route('applications.store'), $this->storePayload('owner.person@example.com', 'Stranger Person'));

        $response->assertRedirect(route('apply'));
        $response->assertSessionHasErrors(['apply' => Application::DUPLICATE_EMAIL_NOTICE]);
        $this->assertSame(1, Application::query()->where('user_id', $owner->id)->count());
        $this->assertSame(0, Application::query()->where('user_id', $stranger->id)->count());
    }

    /** Published/closed own applications resume to the dashboard, never an incomplete step. */
    public function test_published_application_resumes_to_dashboard_not_incomplete_workflow(): void
    {
        $member = User::factory()->create(['email' => 'published.person@example.com', 'role' => User::ROLE_MEMBER]);
        $published = Application::factory()->create([
            'user_id' => $member->id,
            'applicant_email_normalized' => 'published.person@example.com',
            'status' => Application::STATUS_PUBLISHED,
            'full_name' => 'Published Person',
        ]);

        $response = $this->actingAs($member)->post(route('applications.store'), $this->storePayload('published.person@example.com'));

        $response->assertRedirect(route('applications.show', ['application' => $published->id]));
        $this->assertNull(session('errors'));
        $this->assertSame(1, Application::query()->where('user_id', $member->id)->count());
    }

    /** Identical personal names never block registration. */
    public function test_identical_personal_names_do_not_block_registration(): void
    {
        User::factory()->create(['email' => 'first@example.com', 'name' => 'Anil Kumar', 'role' => User::ROLE_MEMBER]);

        $second = $this->post('/register', [
            'name' => 'Anil Kumar',
            'username' => 'anil.kumar.k',
            'email' => 'second@example.com',
            'password' => 'Br1ght-P4ssw0rd!',
            'password_confirmation' => 'Br1ght-P4ssw0rd!',
        ]);

        $second->assertRedirect();
        $this->assertNotNull(User::query()->where('email', 'second@example.com')->first());
        $this->assertSame(2, User::query()->where('name', 'Anil Kumar')->count());
    }

    /** Registration duplicate errors identify the USERNAME specifically. */
    public function test_registration_identifies_taken_username(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'username' => 'anil.kumar.k', 'role' => User::ROLE_MEMBER]);

        $this->post('/register', [
            'name' => 'Someone Else',
            'username' => 'anil.kumar.k',
            'email' => 'b@example.com',
            'password' => 'Br1ght-P4ssw0rd!',
            'password_confirmation' => 'Br1ght-P4ssw0rd!',
        ])->assertSessionHasErrors([
            'username' => 'This username is already registered. Please choose a different username.',
        ]);
    }

    /** Registration duplicate errors identify the EMAIL specifically. */
    public function test_registration_identifies_taken_email(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'username' => 'anil.kumar.k', 'role' => User::ROLE_MEMBER]);

        $this->post('/register', [
            'name' => 'Someone Else',
            'username' => 'different.user',
            'email' => 'a@example.com',
            'password' => 'Br1ght-P4ssw0rd!',
            'password_confirmation' => 'Br1ght-P4ssw0rd!',
        ])->assertSessionHasErrors([
            'email' => 'This email address is already registered. Please sign in instead.',
        ]);
    }

    /** An email tied to an existing APPLICATION is identified at registration. */
    public function test_registration_identifies_email_associated_with_application(): void
    {
        $member = User::factory()->create(['email' => 'account@example.com', 'role' => User::ROLE_MEMBER]);
        Application::factory()->create([
            'user_id' => $member->id,
            'applicant_email_normalized' => 'app-email@example.com',
            'preferred_contact_email' => 'app-email@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ]);

        $this->post('/register', [
            'name' => 'Someone Else',
            'username' => 'fresh.username',
            'email' => 'app-email@example.com',
            'password' => 'Br1ght-P4ssw0rd!',
            'password_confirmation' => 'Br1ght-P4ssw0rd!',
        ])->assertSessionHasErrors([
            'email' => 'This email address is already associated with an existing application.',
        ]);
        $this->assertNull(User::query()->where('email', 'app-email@example.com')->first());
    }

    /** The JSON API contract is unchanged: own-application reposts still 409. */
    public function test_json_contract_unchanged_for_own_application_repost(): void
    {
        $member = User::factory()->create(['email' => 'json.person@example.com', 'role' => User::ROLE_MEMBER]);
        Application::factory()->create([
            'user_id' => $member->id,
            'applicant_email_normalized' => 'json.person@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
            'full_name' => 'Json Person',
        ]);

        $this->actingAs($member)->postJson(route('applications.store'), $this->storePayload('json.person@example.com'))
            ->assertStatus(409)
            ->assertJsonPath('error', Application::DUPLICATE_EMAIL_NOTICE)
            ->assertJsonPath('application_id', function ($value) use ($member): bool {
                $existing = Application::query()->where('user_id', $member->id)->first();

                return (int) $value === (int) $existing->id;
            });
    }
}

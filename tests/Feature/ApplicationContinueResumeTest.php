<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Published profile fixture with one approved photograph (mirrors the
     * real dashboard's expectations for a published application).
     */
    private function seedPublishedWithPhoto(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = \App\Models\Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'full_name' => 'Dashboard Fixture',
            'preferred_display_name' => 'Dashboard Fixture',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Dashboard Fixture',
            'display_name' => 'Dashboard Fixture',
            'slug' => 'dash-fixture-'.\Illuminate\Support\Str::random(8),
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $photo = app(\App\Services\ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            \Illuminate\Http\UploadedFile::fake()->image('portrait.jpg', 400, 500),
            $editor,
        );
        app(\App\Services\ProfileMediaService::class)->approve($photo, $reviewer);

        $english = \App\Models\EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => \App\Models\EditorialContent::LANGUAGE_EN,
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Dashboard Fixture',
            'body' => 'Body.',
            'summary' => 'Summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => \App\Models\Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(\App\Services\ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return ['member' => $member, 'profile' => $profile->fresh(), 'application' => $application->fresh()];
    }

    /**
     * The email-status endpoint returns a single GENERIC boolean — it must
     * not reveal whether an email belongs to an account or an application.
     */
    public function test_email_status_endpoint_reports_associations(): void
    {
        $owner = User::factory()->create(['email' => 'status.person@example.com', 'role' => User::ROLE_MEMBER]);
        Application::factory()->create([
            'user_id' => $owner->id,
            'applicant_email_normalized' => 'status.person@example.com',
            'preferred_contact_email' => 'status.person@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ]);

        // Unauthenticated applicants fill the intake form — the endpoint is public.
        $this->getJson(route('apply.email-status', ['email' => 'status.person@example.com']))
            ->assertOk()
            ->assertJson(['email_in_use' => true])
            ->assertJsonMissing(['account_exists' => true])
            ->assertJsonMissing(['application_associated' => true]);

        // An email associated ONLY with an application must be indistinguishable
        // from one tied to an account: seed an application whose owner has a
        // different account email.
        Application::factory()->create([
            'user_id' => $owner->id,
            'applicant_email_normalized' => 'only.application@example.com',
            'preferred_contact_email' => 'only.application@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ]);

        $this->getJson(route('apply.email-status', ['email' => 'only.application@example.com']))
            ->assertOk()
            ->assertJson(['email_in_use' => true]);

        $this->getJson(route('apply.email-status', ['email' => 'brand.new@example.com']))
            ->assertOk()
            ->assertJson(['email_in_use' => false])
            ->assertJsonMissing(['account_exists'])
            ->assertJsonMissing(['application_associated']);
    }

    /** Registration still distinguishes account-level email duplicates. */
    public function test_registration_rejects_account_email_without_status_leak(): void
    {
        User::factory()->create(['email' => 'status.person@example.com', 'role' => User::ROLE_MEMBER]);

        $this->post('/register', [
            'name' => 'Late Applicant',
            'username' => 'late.applicant',
            'email' => 'status.person@example.com',
            'password' => 'Br1ght-P4ssw0rd!',
            'password_confirmation' => 'Br1ght-P4ssw0rd!',
        ])->assertSessionHasErrors(['email']);

        $this->assertSame(1, User::query()->where('email', 'status.person@example.com')->count());
    }

    /** Cross-account duplicates surface a field-specific error on the email field. */
    public function test_store_duplicate_error_is_field_specific(): void
    {
        $owner = User::factory()->create(['email' => 'taken.person@example.com', 'role' => User::ROLE_MEMBER]);
        Application::factory()->create([
            'user_id' => $owner->id,
            'applicant_email_normalized' => 'taken.person@example.com',
            'status' => Application::STATUS_PUBLISHED,
        ]);

        $stranger = User::factory()->create(['email' => 'stranger.2@example.com', 'role' => User::ROLE_MEMBER]);

        $this->actingAs($stranger)->post(route('applications.store'), $this->storePayload('taken.person@example.com', 'Stranger Two'))
            ->assertRedirect(route('apply'))
            ->assertSessionHasErrors(['email' => Application::DUPLICATE_EMAIL_NOTICE]);
        $this->assertSame(1, Application::query()->where('user_id', $owner->id)->count());
    }

    /** An authenticated user visiting /login lands on their OWN dashboard only. */
    public function test_login_page_redirects_authenticated_user_to_own_dashboard(): void
    {
        $member = User::factory()->create(['email' => 'dash.person@example.com', 'role' => User::ROLE_MEMBER]);
        Application::factory()->create([
            'user_id' => $member->id,
            'applicant_email_normalized' => 'dash.person@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
            'full_name' => 'Dash Person',
        ]);

        $response = $this->actingAs($member)->get('/login');

        $response->assertRedirect(route('applications.show', ['application' => $member->applications->first()->id]));
    }

    /** A different user's application dashboard is refused. */
    public function test_show_own_refuses_foreign_application(): void
    {
        ['profile' => $profile, 'application' => $ownerApplication] = $this->seedPublishedWithPhoto();
        $intruder = User::factory()->create(['role' => User::ROLE_MEMBER]);

        $this->actingAs($intruder)->get(route('applications.show', ['application' => $ownerApplication->id]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors();
        $this->actingAs($intruder)->get(route('applications.payment', ['application' => $ownerApplication->id]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors();
    }

    /** Published profile dashboards show the published state, never payment prompts. */
    public function test_published_application_dashboard_shows_published_not_payment(): void
    {
        ['member' => $member, 'profile' => $profile, 'application' => $application] = $this->seedPublishedWithPhoto();

        $response = $this->actingAs($member)->get(route('applications.show', ['application' => $application->id]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('PUBLISHED', $html);
        $this->assertStringContainsString('View published profile', $html);
        $this->assertStringNotContainsString('Pay to unlock interview', $html);
    }

    /** Genuine unpaid, not-yet-published applications keep the payment prompt. */
    public function test_unpaid_incomplete_application_keeps_payment_prompt(): void
    {
        $member = User::factory()->create(['email' => 'unpaid.person@example.com', 'role' => User::ROLE_MEMBER]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Unpaid Person',
            'display_name' => 'Unpaid Person',
            'slug' => 'unpaid-fixture-'.\Illuminate\Support\Str::random(8),
        ]);
        $application = Application::factory()->create([
            'user_id' => $member->id,
            'applicant_email_normalized' => 'unpaid.person@example.com',
            'profile_id' => $profile->id,
            'source_method' => 'online_interview',
            'status' => Application::STATUS_PAYMENT_PENDING,
            'full_name' => 'Unpaid Person',
        ]);

        $response = $this->actingAs($member)->get(route('applications.show', ['application' => $application->id]));

        $response->assertOk();
        $this->assertStringContainsString('Pay to unlock interview', $response->getContent());
    }

    /** Registration rejection for an existing email creates no user and no session. */
    public function test_registration_rejection_creates_no_user(): void
    {
        User::factory()->create(['email' => 'registered@example.com', 'username' => 'registered.user', 'role' => User::ROLE_MEMBER]);

        $this->post('/register', [
            'name' => 'Late Applicant',
            'username' => 'late.applicant',
            'email' => 'registered@example.com',
            'password' => 'Br1ght-P4ssw0rd!',
            'password_confirmation' => 'Br1ght-P4ssw0rd!',
        ])->assertSessionHasErrors(['email']);

        $this->assertNull(User::query()->where('email', 'registered@example.com')->where('username', 'late.applicant')->first());
        $this->assertSame(1, User::query()->where('email', 'registered@example.com')->count());
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

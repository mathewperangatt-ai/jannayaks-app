<?php

namespace Tests\Feature;

use App\Mail\InvitationRequestMail;
use App\Mail\ProfileContactMail;
use App\Mail\RecommendationReceivedMail;
use App\Models\Application;
use App\Models\Profile;
use App\Models\ProfileReaction;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Final frontend pass: demo profiles, Contact box, Like/Applaud,
 * Recommend Someone, Request an Invitation, and the controlled
 * test-account payment bypass.
 */
class FinalFrontendEngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_demo_profiles_seed_and_render_publicly(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        // Living final demo: T. Gopalakrishnan, Distinguished, corpus text.
        $this->get('/t.gopalakrishnan')
            ->assertOk()
            ->assertSee('T. Gopalakrishnan', false)
            ->assertSee('tier-mark tier-mark--distinguished', false)
            ->assertSee('Social Educator and Community Leader', false)
            ->assertSee('Fictional demonstration profile', false)
            ->assertSee('should eventually become participants in creating opportunities for others', false);

        // Gallery lists the demo with the tier corner marker.
        $this->get('/gallery')
            ->assertOk()
            ->assertSee('T. Gopalakrishnan', false)
            ->assertSee('tier-mark--distinguished', false);

        // Memorial demos render with the sombre treatment and the approved designation.
        foreach (['k.v.mathew' => 'K. V. Mathew', 'dr.saroja.nair' => 'Dr. Saroja Nair'] as $slug => $name) {
            $response = $this->get('/in-memoriam/'.$slug);
            $response->assertOk()->assertSee($name, false);
            $this->assertStringContainsString('ഓർമ്മയ്ക്കായി', $response->getContent());
            $this->assertStringContainsString('im-page', $response->getContent()); // sombre memorial stylesheet
        }

        // Old reference memorial identities are NOT published.
        $this->assertDatabaseMissing('in_memoriam_profiles', ['slug' => 't.v.ramanathan']);
        $this->assertDatabaseMissing('in_memoriam_profiles', ['slug' => 'dr.mary.kurien']);
    }

    public function test_contact_box_delivers_privately_and_stores_message(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        Mail::fake();

        // The owner's (demo) email must never appear in the page source.
        $page = $this->get('/t.gopalakrishnan');
        $page->assertOk();
        $this->assertStringNotContainsString('demo.profiles@jannayaks.internal', $page->getContent());

        $response = $this->post('/profiles/t.gopalakrishnan/contact', [
            'visitor_name' => 'Arun K.',
            'visitor_mobile' => '+91 98765 43210',
            'message' => 'Would like to discuss a community programme invitation.',
        ]);
        $response->assertRedirect('/t.gopalakrishnan#wellwishers');

        $this->assertDatabaseHas('profile_contact_messages', [
            'visitor_name' => 'Arun K.',
            'visitor_mobile' => '+91 98765 43210',
        ]);

        // Notification carries the approved owner wording; recipient resolved server-side.
        Mail::assertSent(ProfileContactMail::class, 1);
        Mail::assertSent(ProfileContactMail::class, function ($mail) {
            return str_contains($mail->envelope()->subject, 'A visitor would like to contact you');
        });

        // Validation rejects junk.
        $this->post('/profiles/t.gopalakrishnan/contact', [
            'visitor_name' => 'A',
            'visitor_mobile' => 'abc',
            'message' => 'hi',
        ])->assertSessionHasErrors();

        // Honeypot silently drops without storing.
        $this->post('/profiles/t.gopalakrishnan/contact', [
            'visitor_name' => 'Bot',
            'visitor_mobile' => '9999999999',
            'message' => 'spam spam spam spam',
            'honey_bot' => 'x',
        ]);
        $this->assertDatabaseMissing('profile_contact_messages', ['visitor_name' => 'Bot']);
    }

    public function test_like_and_applaud_require_login_and_toggle_privately(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $this->post('/profiles/t.gopalakrishnan/react', ['reaction' => 'like'])
            ->assertRedirect(route('login'));

        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::where('slug', 't.gopalakrishnan')->firstOrFail();

        $this->actingAs($user)->post('/profiles/t.gopalakrishnan/react', ['reaction' => 'like'])
            ->assertRedirect();
        $this->assertDatabaseHas('profile_reactions', [
            'profile_id' => $profile->id,
            'user_id' => $user->id,
            'reaction' => 'like',
        ]);

        // Toggle off removes it; applaud coexists independently.
        $this->actingAs($user)->post('/profiles/t.gopalakrishnan/react', ['reaction' => 'like']);
        $this->actingAs($user)->post('/profiles/t.gopalakrishnan/react', ['reaction' => 'applaud']);
        $this->assertSame(1, ProfileReaction::where('profile_id', $profile->id)->count());

        // No public counts/list endpoint exists.
        $this->get('/t.gopalakrishnan')->assertOk();
        $this->assertSame(0, collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($r) => str_contains($r->getName() ?? '', 'reaction'))
            ->count());

        // Invalid reaction kind is rejected.
        $this->actingAs($user)->post('/profiles/t.gopalakrishnan/react', ['reaction' => 'party'])->assertSessionHasErrors();
    }

    public function test_recommend_someone_flow(): void
    {
        Mail::fake();

        $this->get('/recommend')
            ->assertOk()
            ->assertSee('Recommend Someone You May Know', false)
            ->assertSee('does not guarantee inclusion', false);

        $this->post('/recommend', [
            'recommender_name' => 'Deepa Menon',
            'recommender_contact' => 'deepa@example.com',
            'recommended_name' => 'Ravi Chandran',
            'recommended_location' => 'Kollam',
            'recommended_role' => 'Teacher and youth mentor',
            'reason' => 'Two decades of quiet community teaching and library work in her town.',
            'acknowledged_terms' => '1',
        ])->assertRedirect(route('recommend.show'));

        $this->assertDatabaseHas('recommendations', ['recommended_name' => 'Ravi Chandran']);
        Mail::assertSent(RecommendationReceivedMail::class, 1);

        // Terms acknowledgement is mandatory.
        $this->post('/recommend', [
            'recommender_name' => 'Deepa Menon',
            'recommender_contact' => 'deepa@example.com',
            'recommended_name' => 'Someone Else',
            'reason' => 'A long enough reason statement for validation.',
        ])->assertSessionHasErrors();
    }

    public function test_request_invitation_flow_is_separate_from_recommend(): void
    {
        Mail::fake();

        $this->get('/request-invitation')
            ->assertOk()
            ->assertSee('Request an Invitation', false)
            ->assertSee('Recommend Someone You May Know', false); // cross-link, separate destination

        $this->post('/request-invitation', [
            'name' => 'Sara Thomas',
            'contact' => 'sara@example.com',
            'town' => 'Kottayam',
            'role' => 'Public health coordinator',
            'reason' => 'Fifteen years running rural screening camps across the district.',
            'acknowledged_terms' => '1',
        ])->assertRedirect(route('invitation-request.show'));

        $this->assertDatabaseHas('invitation_requests', ['name' => 'Sara Thomas']);
        Mail::assertSent(InvitationRequestMail::class, 1);
    }

    public function test_controlled_test_account_bypass_unlocks_interview_without_payment(): void
    {
        // Server-side admin_test_demo waiver path — never query-string or
        // frontend controllable.
        $staff = User::factory()->admin()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->adminTestDemo($staff->id)->for($user)->create([
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
        ]);

        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application));

        // An ordinary pending application stays locked.
        $ordinary = Application::factory()->for(User::factory()->create())->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($ordinary));

        // The public bypass pattern must not exist as a route.
        $this->get('/apply/continue?skip_payment=1')->assertRedirect(route('login'));
        $this->assertSame(
            0,
            collect(Route::getRoutes()->getRoutesByName())
                ->filter(fn ($r) => str_contains($r->uri(), 'skip'))
                ->count()
        );
    }
}

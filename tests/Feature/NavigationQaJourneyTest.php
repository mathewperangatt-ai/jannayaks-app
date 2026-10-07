<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FINAL QA — navigation + Malayalam tier names (A–H).
 *
 * A  Homepage → Sign In reaches the sign-in route and works logged out.
 * B  Homepage → Register Profile reaches the correct entry (intro → tiers).
 * C  Register Profile → Online Interview proceeds into the existing
 *    interview flow, with payment required first.
 * D  Register → Payment lands on the existing payment page after the
 *    application is created (register continuation of the guest intent).
 * E  Payment-before-interview gate on the existing Razorpay architecture.
 * F  Entered data (tier, intake source, name, contacts) is preserved.
 * G  No duplicate application is created (A2 guard).
 * H  Approved Malayalam tier names render exactly — never the banned variants.
 */
class NavigationQaJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const INTENT = [
        'package_tier' => 'distinguished',
        'source_method' => 'online_interview',
        'full_name' => 'Navigation QA Test',
        'preferred_slug' => 'navigation-qa',
        'contact_email' => 'nav.qa@example.com',
    ];

    private const REGISTRATION = [
        'name' => 'Navigation QA Test',
        'username' => 'navqa.test',
        'email' => 'nav.qa@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
    ];

    /* A — Homepage → Sign In. */
    public function test_a_homepage_sign_in_reaches_login_and_works_logged_out(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sign In')
            ->assertSee('/login', false);

        $loginPage = $this->get(route('login'));
        $loginPage->assertOk()->assertSee('Sign in to Jannayaks');

        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'correct-horse-42']);
        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'correct-horse-42',
        ])->assertRedirect(route('apply'));
    }

    /* B — Homepage → Register Profile entry. */
    public function test_b_homepage_register_profile_reaches_intro_then_tiers(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/apply', false);

        $this->get(route('apply'))
            ->assertOk()
            ->assertSee('Tell us about a life worth remembering.')
            ->assertSee(route('apply', ['step' => 'tiers']));

        $this->get(route('apply', ['step' => 'tiers']))
            ->assertOk()
            ->assertSee('Continue to sign in');
    }

    /* C + D — Guest tier form → register → application → payment page,
       and the Online Interview stays behind the payment gate. */
    public function test_c_d_register_continues_intent_to_payment_then_gates_interview(): void
    {
        $this->withSession(['apply.intent' => self::INTENT]);

        $res = $this->post(route('register'), self::REGISTRATION);
        $res->assertRedirect();

        $app = Application::query()->latest('id')->first();
        $this->assertNotNull($app, 'Application should be created from the pending intent.');
        $res->assertRedirect(route('applications.payment', ['application' => $app->id]));

        // Existing interview flow, gated until payment settles.
        $this->actingAs($app->user)
            ->get(route('online-interview.show', ['application' => $app->id]))
            ->assertRedirect(route('applications.payment', ['application' => $app->id]));
    }

    /* E — Payment-before-interview, explicitly. */
    public function test_e_unpaid_owner_is_redirected_to_payment_from_interview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'correct-horse-42']);
        $app = $this->createPaymentPendingApplication($user);

        $this->actingAs($user)
            ->get(route('online-interview.show', ['application' => $app->id]))
            ->assertRedirect(route('applications.payment', ['application' => $app->id]));

        $this->actingAs($user)
            ->getJson(route('online-interview.show', ['application' => $app->id]))
            ->assertStatus(403)
            ->assertJsonPath('redirect_to', route('applications.payment', ['application' => $app->id]));
    }

    /* F — Entered data is preserved through the intent → application. */
    public function test_f_intent_data_is_preserved_in_created_application(): void
    {
        $this->withSession(['apply.intent' => self::INTENT]);
        $this->post(route('register'), self::REGISTRATION)->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'user_id' => User::where('email', 'nav.qa@example.com')->value('id'),
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'full_name' => 'Navigation QA Test',
            'preferred_slug' => 'navigation-qa',
            'preferred_contact_email' => 'nav.qa@example.com',
        ]);
    }

    /* G — No duplicate application (A2). */
    public function test_g_second_intent_does_not_create_a_second_application(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'correct-horse-42']);
        $app = $this->createPaymentPendingApplication($user);

        $this->actingAs($user)
            ->postJson(route('apply.intent'), [
                'package_tier' => 'emerging',
                'source_method' => 'online_interview',
                'full_name' => 'Navigation QA Test',
                'contact_email' => 'nav.qa@example.com',
            ])
            ->assertStatus(409)
            ->assertJsonPath('application_id', $app->id);

        $this->assertSame(1, Application::query()->where('user_id', $user->id)->count());
    }

    /* H — Approved Malayalam tier names, exactly. */
    public function test_h_tier_page_uses_approved_malayalam_names_only(): void
    {
        $tiersPage = $this->get(route('apply', ['step' => 'tiers']))->assertOk();

        foreach (['ജനകീയർ', 'ജനസമ്മതർ', 'പ്രമുഖർ'] as $approved) {
            $tiersPage->assertSee($approved, false);
        }
        foreach (['അംഗീകരിക്കപ്പെട്ടവർ', 'പ്രശസ്തർ', 'വിശിഷ്ടർ'] as $banned) {
            $tiersPage->assertDontSee($banned, false);
        }

        $faq = $this->get(route('faq-charges'))->assertOk();
        $faq->assertSee('പ്രമുഖർ', false);
        $faq->assertDontSee('പ്രശസ്തർ', false);
    }

    /* Login (existing account) also continues the pending intent. */
    public function test_login_continues_pending_intent_to_payment(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'correct-horse-42']);

        $this->withSession(['apply.intent' => self::INTENT]);
        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'correct-horse-42',
        ])->assertRedirect();

        $app = Application::query()->latest('id')->first();
        $this->assertNotNull($app);
        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'package_tier' => 'distinguished',
        ]);

        $this->actingAs($app->user)
            ->get(route('applications.payment', ['application' => $app->id]))
            ->assertOk()
            // The payment page renders Malayalam by default (lang switcher),
            // so assert on the application number, which both languages show.
            ->assertSee('#'.$app->id);
    }

    private function createPaymentPendingApplication(User $user): Application
    {
        $this->actingAs($user)->post(route('applications.store'), [
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'full_name' => 'Navigation QA Test',
            'contact_email' => 'nav.qa@example.com',
        ])->assertRedirect();

        /** @var Application $app */
        $app = Application::query()->latest('id')->first();
        $this->assertSame('payment_pending', (string) $app->status);

        return $app;
    }
}

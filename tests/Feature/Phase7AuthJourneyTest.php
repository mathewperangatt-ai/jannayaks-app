<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\MobileOtpService;
use App\Support\IndiaMobile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class Phase7AuthJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google', false)
            ->assertSee('Indian mobile OTP', false);
    }

    public function test_google_callback_creates_verified_member_and_logs_in(): void
    {
        $this->mockGoogleUser('google-abc-123', 'member@example.com', 'Member Example');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('apply'));

        $this->assertAuthenticated();
        /** @var User $user */
        $user = Auth::user();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('member@example.com', $user->email);
        $this->assertSame('google-abc-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(User::ROLE_MEMBER, $user->role);
    }

    public function test_google_cannot_take_over_unverified_email_account(): void
    {
        $existing = User::factory()->unverified()->create([
            'email' => 'victim@example.com',
            'google_id' => null,
            'email_verified_at' => null,
        ]);

        $this->mockGoogleUser('google-attacker', 'victim@example.com', 'Attacker Name');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $existing->refresh();
        $this->assertNull($existing->google_id);
        $this->assertNull($existing->email_verified_at);
    }

    public function test_google_links_verified_email_account_safely(): void
    {
        $existing = User::factory()->create([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
            'google_id' => null,
            'name' => 'Prior Name',
        ]);

        $this->mockGoogleUser('google-link-1', 'verified@example.com', 'Google Name');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('apply'));

        $this->assertAuthenticatedAs($existing->fresh());
        $existing->refresh();
        $this->assertSame('google-link-1', $existing->google_id);
        $this->assertNotNull($existing->email_verified_at);
    }

    public function test_google_blocks_conflicting_google_id_on_verified_email(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
            'email_verified_at' => now(),
            'google_id' => 'google-original',
        ]);

        $this->mockGoogleUser('google-other', 'taken@example.com', 'Other');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_google_login_regenerates_session(): void
    {
        $this->startSession();
        $before = session()->getId();
        $this->mockGoogleUser('google-sess-1', 'sess@example.com', 'Sess User');

        $this->get(route('auth.google.callback'))->assertRedirect(route('apply'));

        $this->assertAuthenticated();
        $this->assertNotSame($before, session()->getId());
    }

    public function test_google_intended_redirect_rejects_external_url(): void
    {
        $this->withSession(['url.intended' => 'https://evil.example/phish']);
        $this->mockGoogleUser('google-redir-1', 'redir@example.com', 'Redir User');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('apply'));
    }

    public function test_india_otp_login_rejects_non_india_numbers(): void
    {
        $this->post(route('auth.otp.send'), ['mobile' => '+1 4155552671'])
            ->assertSessionHasErrors('mobile');
    }

    public function test_india_otp_request_and_verify_logs_in_member(): void
    {
        $this->post(route('auth.otp.send'), ['mobile' => '9876543210'])
            ->assertRedirect(route('auth.otp.verify.show'));

        $mobile = IndiaMobile::normalize('9876543210');
        $code = app(MobileOtpService::class)->testCodeFor($mobile);
        $this->assertNotNull($code);

        $this->post(route('auth.otp.verify'), [
            'mobile' => $mobile,
            'otp' => $code,
        ])->assertRedirect(route('apply'));

        $this->assertAuthenticated();
        $user = User::query()->where('mobile', $mobile)->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->mobile_verified_at);
    }

    public function test_otp_login_regenerates_session(): void
    {
        $this->startSession();
        $before = session()->getId();

        $this->post(route('auth.otp.send'), ['mobile' => '9876500001']);
        $mobile = IndiaMobile::normalize('9876500001');
        $code = app(MobileOtpService::class)->testCodeFor($mobile);

        $this->post(route('auth.otp.verify'), [
            'mobile' => $mobile,
            'otp' => $code,
        ])->assertRedirect(route('apply'));

        $this->assertAuthenticated();
        $this->assertNotSame($before, session()->getId());
    }

    public function test_otp_cannot_be_reused(): void
    {
        $this->post(route('auth.otp.send'), ['mobile' => '9876500002']);
        $mobile = IndiaMobile::normalize('9876500002');
        $code = app(MobileOtpService::class)->testCodeFor($mobile);
        $this->assertNotNull($code);

        $this->post(route('auth.otp.verify'), [
            'mobile' => $mobile,
            'otp' => $code,
        ])->assertRedirect(route('apply'));

        Auth::logout();
        $this->flushSession();

        $this->post(route('auth.otp.verify'), [
            'mobile' => $mobile,
            'otp' => $code,
        ])->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_otp_verification_has_per_ip_rate_limiting(): void
    {
        RateLimiter::clear('otp-verify:ip:127.0.0.1');
        RateLimiter::clear('otp-request:ip:127.0.0.1');

        $svc = app(MobileOtpService::class);

        for ($i = 0; $i < 20; $i++) {
            RateLimiter::clear('otp-request:ip:127.0.0.1');
            $mobile = '98765'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $svc->request($mobile, '127.0.0.1');
            try {
                $svc->verify($mobile, '000000', '127.0.0.1');
            } catch (\Illuminate\Validation\ValidationException) {
                // expected invalid OTP
            }
        }

        RateLimiter::clear('otp-request:ip:127.0.0.1');
        $svc->request('9876599999', '127.0.0.1');

        try {
            $svc->verify('9876599999', '000000', '127.0.0.1');
            $this->fail('Expected per-IP OTP verification rate limit to throw.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('otp', $e->errors());
            $this->assertStringContainsString('network', strtolower(implode(' ', $e->errors()['otp'])));
        }
    }

    public function test_member_cannot_access_filament_admin_panel(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $admin = User::factory()->admin()->create();

        $this->assertFalse($member->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($member)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guest_can_select_tier_then_must_login_before_application(): void
    {
        $res = $this->post(route('apply.intent'), [
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Guest Tier User',
            'contact_email' => 'guest.tier@example.com',
        ]);

        $res->assertRedirect();
        $this->assertTrue(str_contains($res->headers->get('Location'), '/login'));
        $this->assertNotNull(session('apply.intent'));
    }

    public function test_after_login_intent_creates_application_and_sends_to_payment(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('apply.intent'), [
            'package_tier' => 'accomplished',
            'source_method' => 'online_interview',
            'full_name' => 'Paid Path User',
            'contact_email' => 'paid.path@example.com',
        ])->assertRedirect();

        $app = Application::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($app);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $app->payment_status);
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $app->status);
    }

    public function test_forged_payment_status_in_store_cannot_unlock_interview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $res = $this->actingAs($user)->postJson(route('applications.store'), [
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Forge Status',
            'contact_email' => 'forge.status@example.com',
            'payment_status' => 'paid',
            'status' => 'published',
            'waived_by_user_id' => 1,
        ]);
        $res->assertCreated();
        $app = Application::query()->findOrFail((int) $res->json('application_id'));
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $app->payment_status);
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $app->status);
        $this->assertNull($app->waived_by_user_id);

        $this->actingAs($user)
            ->get(route('online-interview.show', $app))
            ->assertRedirect(route('applications.payment', $app));
    }

    public function test_unpaid_user_cannot_open_interview_or_uploads(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        $app->forceFill(['payment_status' => Application::PAYMENT_STATUS_PENDING])->save();

        $this->actingAs($user)
            ->get(route('online-interview.show', $app))
            ->assertRedirect(route('applications.payment', $app));

        $this->actingAs($user)
            ->getJson(route('online-interview.show', $app))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('applications.upload.show', $app))
            ->assertRedirect(route('applications.payment', $app));
    }

    public function test_unpaid_interview_save_and_submit_are_blocked(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);

        $this->actingAs($user)->patchJson(route('online-interview.save', $app), [
            'question_id' => 'q1',
            'answer' => 'Should not save',
        ])->assertForbidden();

        $this->actingAs($user)->postJson(route('online-interview.submit', $app))
            ->assertForbidden();
    }

    public function test_unpaid_upload_post_is_blocked(): void
    {
        Storage::fake('private_uploads');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);

        $file = UploadedFile::fake()->create('bio.pdf', 100, 'application/pdf');
        $this->actingAs($user)->postJson(route('applications.upload.material', $app), [
            'material' => $file,
            'material_type' => 'biography',
        ])->assertForbidden();
    }

    public function test_forged_application_payment_status_paid_without_payment_row_does_not_unlock(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        $app->forceFill([
            'payment_status' => Application::PAYMENT_STATUS_PAID,
            'payment_settled_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get(route('online-interview.show', $app))
            ->assertRedirect(route('applications.payment', $app));
    }

    public function test_paid_or_waived_user_can_open_interview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $paid = Application::factory()->for($user)->paid()->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        $this->actingAs($user)->get(route('online-interview.show', $paid))->assertOk();

        $admin = User::factory()->admin()->create();
        $waived = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        $waived->forceFill([
            'waived_by_user_id' => $admin->id,
            'payment_status' => Application::PAYMENT_STATUS_WAIVED,
        ])->save();
        $this->actingAs($user)->get(route('online-interview.show', $waived))->assertOk();
    }

    public function test_guest_redirect_uses_member_login_not_filament(): void
    {
        $this->get(route('apply.continue'))
            ->assertRedirect(route('login'));
    }

    private function mockGoogleUser(string $id, string $email, string $name): void
    {
        $social = Mockery::mock(SocialiteUser::class);
        $social->shouldReceive('getId')->andReturn($id);
        $social->shouldReceive('getEmail')->andReturn($email);
        $social->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($social);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}

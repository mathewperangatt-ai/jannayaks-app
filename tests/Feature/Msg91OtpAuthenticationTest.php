<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Msg91WidgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * MSG91 OTP Widget authentication — fail-closed verification of the widget
 * access token; only MSG91's verified mobile number authenticates a user.
 * All MSG91 HTTP traffic is faked; the live service is never contacted.
 */
class Msg91OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'https://control.msg91.com/api/v5/widget/verifyAccessToken';

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.otp.login_enabled' => true]);
        config(['jannayaks.otp.msg91.auth_key' => 'test-server-auth-key']);
        config(['jannayaks.otp.msg91.widget_id' => 'SecureOTPWidgetYLBE']);
        config(['jannayaks.otp.msg91.widget_token' => 'test-widget-token']);
    }

    /* 1. Kill switch disabled → existing disabled behaviour remains. */
    public function test_disabled_kill_switch_redirects_and_hides_widget(): void
    {
        config(['jannayaks.otp.login_enabled' => false]);

        $this->get(route('auth.otp.request.show'))->assertRedirect(route('login'));
        $this->post(route('auth.otp.verify'), ['access_token' => 'anything'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /* Widget page renders with the non-secret widget values when enabled. */
    public function test_widget_page_renders_when_configured(): void
    {
        $this->get(route('auth.otp.request.show'))
            ->assertOk()
            ->assertSee('Sign in with Indian mobile OTP')
            ->assertSee('verify.msg91.com')
            ->assertSee('SecureOTPWidgetYLBE')
            ->assertDontSee('test-server-auth-key'); // AuthKey never reaches the browser.
    }

    public function test_widget_page_shows_unavailable_when_widget_not_configured(): void
    {
        config(['jannayaks.otp.msg91.widget_token' => '']);

        $this->get(route('auth.otp.request.show'))
            ->assertOk()
            ->assertSee('temporarily unavailable');
    }

    /* 2. Missing server credentials → fail closed. */
    public function test_missing_auth_key_fails_closed(): void
    {
        config(['jannayaks.otp.msg91.auth_key' => '']);

        $response = $this->post(route('auth.otp.verify'), ['access_token' => 'some-jwt']);

        $response->assertRedirect(route('auth.otp.request.show'))
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
        Http::assertNothingSent(); // Must not even attempt the call.
    }

    /* 3. Missing access token → rejected. */
    public function test_missing_access_token_is_rejected(): void
    {
        $response = $this->post(route('auth.otp.verify'), []);

        $response->assertSessionHasErrors('access_token');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    /* 4. MSG91 verification failure → rejected. */
    public function test_provider_failure_rejects_authentication(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'error', 'message' => 'Invalid token'], 401)]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'bad-token'])
            ->assertRedirect(route('auth.otp.request.show'))
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_provider_unreachable_rejects_authentication(): void
    {
        Http::fake([self::VERIFY_URL => Http::failedConnection('connection refused')]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'any-token'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    /* 5. Successful verification → the correct existing member is authenticated. */
    public function test_successful_verification_logs_in_existing_member(): void
    {
        $member = User::factory()->create([
            'mobile' => '919876543210',
            'mobile_verified_at' => now(),
        ]);
        Http::fake([self::VERIFY_URL => Http::response([
            'type' => 'success',
            'message' => 'Token verified successfully',
            'mobile' => '919876543210',
        ])]);

        $response = $this->post(route('auth.otp.verify'), ['access_token' => 'valid-jwt']);

        $response->assertRedirect(route('apply'));
        $this->assertAuthenticatedAs($member);
        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type')
                && $request->data()['authkey'] === 'test-server-auth-key'
                && $request->data()['access-token'] === 'valid-jwt';
        });
    }

    /* New verified mobile creates exactly one minimal member account (existing design). */
    public function test_new_verified_mobile_creates_single_member_account(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => '919812345678'])]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'valid-jwt'])->assertRedirect(route('apply'));

        $user = User::query()->where('mobile', '919812345678')->firstOrFail();
        $this->assertSame(User::ROLE_MEMBER, (string) $user->role);
        $this->assertTrue((bool) $user->mobile_verified_at);
        $this->assertSame(1, User::query()->where('mobile', '919812345678')->count());
    }

    /* 6. No duplicate accounts for the same verified mobile. */
    public function test_repeated_logins_do_not_create_duplicate_accounts(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => '919812345678'])]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt-1'])->assertRedirect(route('apply'));
        Auth::logout();
        $this->flushSession();

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt-2'])
            ->assertRedirect(route('apply'));

        $this->assertSame(1, User::query()->where('mobile', '919812345678')->count());
    }

    /* 7. Unexpected response shapes → rejected safely. */
    public function test_unexpected_response_shape_is_rejected(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['something' => 'odd'], 200)]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_non_indian_mobile_in_response_is_rejected(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => '+14155552671'])]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_success_without_explicit_success_indication_is_rejected(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['mobile' => '919812345678'], 200)]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_suspended_member_cannot_login_via_msg91(): void
    {
        User::factory()->suspended()->create(['mobile' => '919876543210']);
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => '919876543210'])]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    /* 8. AuthKey / access token never appear in application logs. */
    public function test_secrets_and_tokens_are_never_logged(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'error'], 401)]);

        $logged = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($channel, $context = []) use (&$logged) {
            $logged[] = json_encode([$channel, $context]);

            return true;
        });
        Log::shouldReceive('info')->andReturnFalse();

        $this->post(route('auth.otp.verify'), ['access_token' => 'secret-bearer-jwt'])
            ->assertSessionHasErrors('otp');

        $this->assertNotEmpty($logged);
        foreach ($logged as $entry) {
            $this->assertStringNotContainsString('secret-bearer-jwt', $entry, 'Access token must never be logged.');
            $this->assertStringNotContainsString('test-server-auth-key', $entry, 'AuthKey must never be logged.');
        }
    }

    /* Session regeneration on widget login. */
    public function test_msg91_login_regenerates_session(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => '919811110000'])]);

        $response = $this->withSession(['_token' => 'old-token-value'])
            ->post(route('auth.otp.verify'), ['access_token' => 'jwt']);

        $response->assertRedirect(route('apply'));
        $this->assertAuthenticated();
        $this->assertNotSame('old-token-value', session()->token());
    }

    /* Server trusts only MSG91's mobile — a client-asserted number is ignored. */
    public function test_client_supplied_mobile_is_ignored(): void
    {
        $other = User::factory()->create(['mobile' => '919999999999']);
        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => '919876543210'])]);

        $this->post(route('auth.otp.verify'), [
            'access_token' => 'jwt',
            'mobile' => '919999999999', // Spoofed field the server never reads.
        ])->assertRedirect(route('apply'));

        $this->assertAuthenticatedAs(User::query()->where('mobile', '919876543210')->firstOrFail());
        $this->assertDatabaseMissing('users', ['id' => $other->id, 'mobile' => '919876543210']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

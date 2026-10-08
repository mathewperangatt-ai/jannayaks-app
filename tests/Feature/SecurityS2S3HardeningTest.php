<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RazorpayPaymentService;
use App\Support\TrustedProxiesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

/**
 * S2/S3 security hardening regressions.
 *
 * 1. CSP enforced by default, MSG91/OTP hosts removed, admin policy adds
 *    'unsafe-eval' for Filament/Alpine, Report-Only escape hatch still works.
 * 2. TRUSTED_PROXIES defaults to Cloudflare + private ranges, never '*'.
 * 3. Razorpay live mode refuses test keys (and test mode refuses live keys).
 * 4. Google OAuth fails safe when client credentials are missing.
 * 5. Password defaults impose a strength baseline (registration/reset).
 * 6. Per-username login throttle bounds distributed credential stuffing.
 */
class SecurityS2S3HardeningTest extends TestCase
{
    use RefreshDatabase;

    /* 1a. CSP is enforced on member pages and carries no MSG91 hosts. */
    public function test_csp_is_enforced_by_default_and_msg91_hosts_are_gone(): void
    {
        $res = $this->get(route('home'));

        $res->assertOk();
        $csp = (string) $res->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $csp, 'Enforced CSP header must be present by default.');
        $this->assertNull($res->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringNotContainsString('msg91', $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp, 'Member policy must not allow unsafe-eval.');
    }

    /* 1b. Escape hatch: csp_enforce=false returns to Report-Only. */
    public function test_csp_falls_back_to_report_only_when_disabled(): void
    {
        config(['jannayaks.security.headers.csp_enforce' => false]);

        $res = $this->get(route('home'));

        $res->assertOk();
        $this->assertNotNull($res->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($res->headers->get('Content-Security-Policy'));
    }

    /* 1c. Admin pages get the Filament policy with 'unsafe-eval'. */
    public function test_admin_pages_use_admin_csp_with_unsafe_eval(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $res = $this->actingAs($admin)->get('/admin');

        $res->assertOk();
        $csp = (string) $res->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $csp);
        $this->assertStringContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('msg91', $csp);
    }

    /* 2. Trusted proxies: tight default; '*' only when explicit. */
    public function test_trusted_proxies_default_is_cloudflare_plus_private_ranges(): void
    {
        $default = TrustedProxiesConfig::parse('');

        $this->assertIsArray($default);
        $this->assertNotSame('*', $default);
        $this->assertContains('173.245.48.0/20', $default, 'Cloudflare IPv4 ranges must be in the default.');
        $this->assertContains('10.0.0.0/8', $default, 'Private platform ranges must be in the default.');
        $this->assertContains('2400:cb00::/32', $default, 'Cloudflare IPv6 ranges must be in the default.');
    }

    public function test_trusted_proxies_explicit_star_and_lists_are_honoured(): void
    {
        $this->assertSame('*', TrustedProxiesConfig::parse('*'));
        $this->assertSame('*', TrustedProxiesConfig::parse('10.0.0.0/8, *'), 'A stray star anywhere means trust-all.');
        $this->assertSame(
            ['1.2.3.4', '5.6.7.0/24'],
            TrustedProxiesConfig::parse('1.2.3.4, 5.6.7.0/24, 1.2.3.4'),
            'Explicit list replaces the default and is de-duplicated.'
        );
    }

    /* 3. Razorpay mode/key coherence (server-side guard, no live calls). */
    public function test_razorpay_live_mode_rejects_test_keys(): void
    {
        $service = app(RazorpayPaymentService::class);

        config([
            'services.razorpay' => [
                'enabled' => true,
                'mode' => 'live',
                'key_id' => 'rzp_test_XXXXXXXXXXXX',
                'key_secret' => 'secret',
                'webhook_secret' => 'whsec',
            ],
        ]);

        try {
            $service->assertConfigPresent($service->config());
            $this->fail('Live mode must refuse rzp_test_ keys.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('live mode requires key_id starting with "rzp_live_"', $e->getMessage());
        }
    }

    public function test_razorpay_live_mode_accepts_live_keys_and_test_mode_rejects_live_keys(): void
    {
        $service = app(RazorpayPaymentService::class);

        config([
            'services.razorpay' => [
                'enabled' => true,
                'mode' => 'live',
                'key_id' => 'rzp_live_YYYYYYYYYYYY',
                'key_secret' => 'secret',
            ],
        ]);
        $service->assertConfigPresent($service->config());
        $this->assertTrue(true, 'Live keys must pass in live mode.');

        config([
            'services.razorpay' => [
                'enabled' => true,
                'mode' => 'test',
                'key_id' => 'rzp_live_YYYYYYYYYYYY',
                'key_secret' => 'secret',
            ],
        ]);

        try {
            $service->assertConfigPresent($service->config());
            $this->fail('Test mode must refuse rzp_live_ keys.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('test mode requires key_id starting with "rzp_test_"', $e->getMessage());
        }
    }

    /* 4. Google OAuth fails safe without credentials. */
    public function test_google_redirect_fails_safe_when_unconfigured(): void
    {
        config(['services.google' => ['client_id' => '', 'client_secret' => '', 'redirect' => null]]);

        $res = $this->get(route('auth.google'));

        $res->assertRedirect(route('login'));
        $this->followRedirects($res)
            ->assertOk()
            ->assertSee('Google sign-in is not available right now');
    }

    public function test_google_redirect_builds_authorize_url_when_configured(): void
    {
        config([
            'services.google' => [
                'client_id' => 'dummy-client-id.apps.googleusercontent.com',
                'client_secret' => 'dummy-secret',
                'redirect' => 'https://jannayaks.test/auth/google/callback',
            ],
        ]);

        $res = $this->get(route('auth.google'));

        $res->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', (string) $res->headers->get('Location'));
        $this->assertStringContainsString('dummy-client-id', (string) $res->headers->get('Location'));
    }

    /* 5. Password strength baseline on registration. */
    public function test_registration_rejects_weak_passwords(): void
    {
        foreach (['1234567890', 'abcdefghij', 'ab1'] as $weak) {
            $res = $this->post(route('register'), [
                'name' => 'Weak Password Test',
                'username' => 'weak-'.substr(md5($weak), 0, 6),
                'email' => 'weak-'.md5($weak).'@example.com',
                'password' => $weak,
                'password_confirmation' => $weak,
            ]);

            $res->assertSessionHasErrors('password', "Password '{$weak}' must be rejected.");
            $this->assertDatabaseMissing('users', ['email' => 'weak-'.md5($weak).'@example.com']);
        }
    }

    public function test_registration_accepts_strong_password(): void
    {
        $res = $this->post(route('register'), [
            'name' => 'Strong Password Test',
            'username' => 'strongpw.test',
            'email' => 'strong.pw@example.com',
            'password' => 'correct-horse-42',
            'password_confirmation' => 'correct-horse-42',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'strong.pw@example.com']);
    }

    /* 6. Per-username login throttle. */
    public function test_distributed_attempts_against_one_username_are_throttled(): void
    {
        $victim = User::factory()->create(['password' => 'correct-horse-42']);
        $key = 'login-username:'.strtolower((string) $victim->username);

        // Simulate attempts from many rotating IPs: the route-level per-IP
        // throttle never sees these; the per-credential counter does.
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit($key, 60);
        }

        $res = $this->post(route('login'), [
            'username' => $victim->username,
            'password' => 'correct-horse-42',
        ]);

        // (In tests there is no referer, so the validation redirect leaves the
        // login page; assert the flashed error rather than the followed HTML.)
        $res->assertSessionHasErrors([
            'username' => 'Too many sign-in attempts. Please wait a minute and try again.',
        ]);

        // A different username from the same IP is not affected: the attempt
        // still fails (wrong password) but with the ordinary message.
        $other = User::factory()->create(['password' => 'correct-horse-42']);
        $this->post(route('login'), [
            'username' => $other->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['username' => 'Invalid username or password.']);
    }

    public function test_successful_login_clears_the_username_throttle(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-42']);
        $key = 'login-username:'.strtolower((string) $user->username);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login'), [
                'username' => $user->username,
                'password' => 'wrong-password',
            ]);
        }
        $this->assertSame(3, RateLimiter::attempts($key));
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 30));

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'correct-horse-42',
        ])->assertRedirect();

        $this->assertSame(0, RateLimiter::attempts($key), 'Successful login must clear the throttle counter.');
    }
}

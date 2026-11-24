<?php

namespace Tests\Feature;

use App\Models\User;
use App\Security\SecurityTotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Wave 3 — mandatory TOTP for the Jannayaks Security Administrator:
 * enrollment gate, per-login challenge, single-use recovery codes,
 * brute-force throttling, and login notifications.
 */
class TotpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeSecurityAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);
    }

    public function test_security_admin_must_enroll_before_panel_access(): void
    {
        $admin = $this->makeSecurityAdmin();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.totp.enroll'));

        $enrolled = $this->totp()->enroll($admin);
        $admin->refresh();
        $code = (new \RobThree\Auth\TwoFactorAuth('Jannayaks'))->getCode($enrolled['secret']);
        $this->assertTrue($this->totp()->confirmEnrollment($admin, $code));
        $this->assertNotNull($admin->refresh()->totp_enabled_at);
    }

    public function test_challenge_requires_a_valid_code(): void
    {
        $admin = $this->makeSecurityAdmin();
        $enrolled = $this->totp()->enroll($admin);
        $admin->refresh();
        $this->totp()->confirmEnrollment($admin, (new \RobThree\Auth\TwoFactorAuth('Jannayaks'))->getCode($enrolled['secret']));

        // Enrollment confirms the session; the panel is reachable.
        $this->actingAs($admin)->get('/admin')->assertOk();

        // A new login resets the per-session verification → challenge again.
        auth()->login($admin);
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.totp.challenge'));

        $this->actingAs($admin)->post(route('security.totp.verify'), ['code' => '000000'])
            ->assertSessionHasErrors();
        $this->assertFalse($this->totp()->isSessionVerified());

        $code = (new \RobThree\Auth\TwoFactorAuth('Jannayaks'))->getCode((string) $admin->totp_secret);
        $this->actingAs($admin)->post(route('security.totp.verify'), ['code' => $code])
            ->assertRedirect('/admin');
        $this->assertTrue($this->totp()->isSessionVerified());
    }

    public function test_recovery_codes_are_single_use(): void
    {
        $admin = $this->makeSecurityAdmin();
        $enrolled = $this->totp()->enroll($admin);
        $admin->refresh();
        $code = (new \RobThree\Auth\TwoFactorAuth('Jannayaks'))->getCode($enrolled['secret']);
        $this->totp()->confirmEnrollment($admin, $code);

        $recovery = $enrolled['recovery_codes'][0];
        $this->assertTrue($this->totp()->verifyChallenge($admin, $recovery), 'First use works.');
        $this->assertFalse($this->totp()->verifyChallenge($admin, $recovery), 'Second use is rejected.');

        $admin->refresh();
        $this->assertCount(7, $admin->totp_recovery_codes);
    }

    public function test_non_security_admin_roles_are_not_forced(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_new_login_notifies_the_account_and_resets_the_challenge(): void
    {
        $admin = $this->makeSecurityAdmin();
        $enrolled = $this->totp()->enroll($admin);
        $admin->refresh();
        $code = (new \RobThree\Auth\TwoFactorAuth('Jannayaks'))->getCode($enrolled['secret']);
        $this->totp()->confirmEnrollment($admin, $code);
        $this->assertTrue($this->totp()->isSessionVerified());

        // A fresh sign-in (event) resets the per-session verification…
        auth()->login($admin);
        $this->assertFalse($this->totp()->isSessionVerified(), 'Each login must re-challenge.');

        // …and emails a new-sign-in notice to the account itself.
        Mail::assertQueued(\App\Mail\SecurityAdminLoginMail::class, 1);
    }

    public function test_totp_challenge_is_throttled(): void
    {
        $admin = $this->makeSecurityAdmin();
        $enrolled = $this->totp()->enroll($admin);
        $admin->refresh();
        $this->totp()->confirmEnrollment($admin, (new \RobThree\Auth\TwoFactorAuth('Jannayaks'))->getCode($enrolled['secret']));

        $this->actingAs($admin);
        // Warm up the session so the rate-limit key is stable across posts.
        $this->get(route('security.totp.challenge'));
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('security.totp.verify'), ['code' => '000000']);
        }

        $response = $this->post(route('security.totp.verify'), ['code' => '000000']);
        $response->assertSessionHasErrors(['code' => 'Too many attempts. Wait a minute and try again.']);
    }

    private function totp(): SecurityTotpService
    {
        return app(SecurityTotpService::class);
    }
}

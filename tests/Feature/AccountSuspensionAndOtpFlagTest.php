<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\IndiaMobile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountSuspensionAndOtpFlagTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'control.msg91.com/api/v5/widget/verifyAccessToken';

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.otp.msg91.auth_key' => 'test-server-auth-key']);
        config(['jannayaks.otp.msg91.widget_id' => 'SecureOTPWidgetYLBE']);
        config(['jannayaks.otp.msg91.widget_token' => 'test-widget-token']);
    }

    public function test_suspended_member_is_blocked_from_member_routes_and_logged_out(): void
    {
        $user = User::factory()->suspended()->create(['email_verified_at' => now()]);
        $this->assertFalse($user->isActiveAccount());

        $this->actingAs($user)->get(route('apply.continue'))
            ->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    public function test_suspended_member_cannot_login_via_otp(): void
    {
        $mobile = IndiaMobile::normalize('9495949399');
        $user = User::factory()->suspended()->create([
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
        ]);
        $this->assertFalse($user->isActiveAccount());

        Http::fake([self::VERIFY_URL => Http::response(['type' => 'success', 'mobile' => $mobile])]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    public function test_active_member_still_logs_in_via_otp(): void
    {
        Http::fake([self::VERIFY_URL => Http::response([
            'type' => 'success',
            'mobile' => IndiaMobile::normalize('9495949398'),
        ])]);

        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertRedirect(route('apply'));

        $this->assertTrue(Auth::check());
    }

    public function test_disabled_otp_flag_hides_routes_and_redirects_to_login(): void
    {
        Config::set('jannayaks.otp.login_enabled', false);

        $this->get(route('auth.otp.request.show'))
            ->assertRedirect(route('login'));
        $this->post(route('auth.otp.verify'), ['access_token' => 'jwt'])
            ->assertRedirect(route('login'));

        $login = $this->get(route('login'));
        $login->assertOk();
        $this->assertStringNotContainsString('Sign in with Indian mobile OTP', (string) $login->getContent());
    }

    public function test_enabled_otp_flag_keeps_login_page_entry_point(): void
    {
        Config::set('jannayaks.otp.login_enabled', true);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in with Indian mobile OTP');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MobileOtpService;
use App\Support\IndiaMobile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AccountSuspensionAndOtpFlagTest extends TestCase
{
    use RefreshDatabase;

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
        $service = app(MobileOtpService::class);
        $mobile = IndiaMobile::normalize('9495949399');
        $user = User::factory()->suspended()->create([
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
        ]);
        $this->assertFalse($user->isActiveAccount());

        $service->request($mobile, '127.0.0.1');
        $code = (string) $service->testCodeFor($mobile);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->post(route('auth.otp.verify'), [
            'mobile' => $mobile,
            'otp' => $code,
        ])->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    public function test_active_member_still_logs_in_via_otp(): void
    {
        $service = app(MobileOtpService::class);
        $mobile = IndiaMobile::normalize('9495949398');

        $service->request($mobile, '127.0.0.1');
        $code = (string) $service->testCodeFor($mobile);

        $this->post(route('auth.otp.verify'), [
            'mobile' => $mobile,
            'otp' => $code,
        ])->assertRedirect(route('apply'));

        $this->assertTrue(Auth::check());
    }

    public function test_disabled_otp_flag_hides_routes_and_redirects_to_login(): void
    {
        Config::set('jannayaks.otp.login_enabled', false);

        $this->get(route('auth.otp.request.show'))
            ->assertRedirect(route('login'));
        $this->post(route('auth.otp.send'), ['mobile' => '9495949397'])
            ->assertRedirect(route('login'));
        $this->get(route('auth.otp.verify.show'))
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

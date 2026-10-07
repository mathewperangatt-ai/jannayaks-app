<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Customer authentication — settled product architecture:
 * username + password primary login, email-based password reset,
 * registration, suspension enforcement, and A1/A2 regression coverage.
 */
class UsernamePasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithApp(array $overrides = []): array
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('correct-horse-42'),
            'username' => strtolower(Str::random(8)),
        ], $overrides));

        $application = Application::factory()->paid()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);

        return [$user, $application];
    }

    // ---------------- Login ----------------

    public function test_user_can_login_with_username_and_password(): void
    {
        [$user] = $this->makeUserWithApp();

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'correct-horse-42',
        ])->assertRedirect();

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_wrong_password_is_refused_with_generic_error(): void
    {
        [$user] = $this->makeUserWithApp();

        $this->from(route('login'))
            ->post(route('login'), [
                'username' => $user->username,
                'password' => 'wrong-password-999',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $errors = session('errors')->all();
        $this->assertStringContainsString('Invalid username or password', implode(' ', $errors));
        $this->assertGuest();
    }

    public function test_unknown_username_is_refused_without_enumeration_hint(): void
    {
        $this->post(route('login'), [
            'username' => 'no-such-user',
            'password' => 'whatever-123',
        ])->assertSessionHasErrors('username');

        $errors = session('errors')->all();
        $this->assertStringContainsString('Invalid username or password', implode(' ', $errors));
    }

    public function test_suspended_user_cannot_authenticate_even_with_valid_password(): void
    {
        [$user] = $this->makeUserWithApp();
        $user->forceFill(['account_status' => 'suspended'])->save();

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'correct-horse-42',
        ])->assertSessionHasErrors('username');

        $errors = session('errors')->all();
        $this->assertStringContainsString('suspended', implode(' ', $errors));
        $this->assertGuest();
    }

    // ---------------- A1: returning customer lands on dashboard ----------------

    public function test_a1_returning_customer_redirects_to_their_application_dashboard(): void
    {
        [$user, $application] = $this->makeUserWithApp();

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'correct-horse-42',
        ])->assertRedirect(route('applications.show', $application));
    }

    public function test_a1_logged_in_user_visiting_login_is_redirected_to_dashboard(): void
    {
        [$user, $application] = $this->makeUserWithApp();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('applications.show', $application));
    }

    public function test_user_without_application_is_sent_to_apply(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('pw-123456'),
            'username' => 'fresh.member',
        ]);

        $this->post(route('login'), [
            'username' => 'fresh.member',
            'password' => 'pw-123456',
        ])->assertRedirect(route('apply'));
    }

    // ---------------- A2: second application blocked ----------------

    public function test_a2_member_cannot_create_a_second_application(): void
    {
        [$user, $application] = $this->makeUserWithApp();
        $before = Application::query()->where('user_id', $user->id)->count();

        $this->actingAs($user)
            ->postJson(route('applications.store'), [
                'package_tier' => 'emerging',
                'source_method' => 'online_interview',
                'full_name' => 'Second Application Attempt',
            ])
            ->assertStatus(409)
            ->assertJson(['application_id' => $application->id]);

        $this->assertSame($before, Application::query()->where('user_id', $user->id)->count());
    }

    // ---------------- Registration ----------------

    public function test_registration_creates_member_with_username_and_logs_in(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->post(route('register.store'), [
            'name' => 'New Member',
            'username' => 'new.member',
            'email' => 'new.member@example.com',
            'password' => 'super-secret-99',
            'password_confirmation' => 'super-secret-99',
        ])->assertRedirect();

        $user = User::query()->where('username', 'new.member')->firstOrFail();
        $this->assertSame('member', $user->role);
        $this->assertSame('active', $user->account_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->password);
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_registration_rejects_duplicate_username_and_email(): void
    {
        User::factory()->create([
            'username' => 'taken.name',
            'email' => 'taken@example.com',
        ]);

        $this->post(route('register.store'), [
            'name' => 'Copycat',
            'username' => 'taken.name',
            'email' => 'free@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertSessionHasErrors('username');

        $this->post(route('register.store'), [
            'name' => 'Copycat',
            'username' => 'free.name',
            'email' => 'taken@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertSessionHasErrors('email');
    }

    // ---------------- Password reset (email-based) ----------------

    public function test_password_reset_link_is_sent_and_generic_response_returned(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user = User::factory()->create([
            'email' => 'resetter@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('old-pass-123'),
            'username' => 'resetter',
        ]);

        $this->post(route('password.email'), ['email' => 'resetter@example.com'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);

        // Unknown email gets the SAME generic status — no enumeration.
        $this->post(route('password.email'), ['email' => 'ghost@example.com'])
            ->assertSessionHasNoErrors();
    }

    public function test_password_reset_sets_new_password_and_invalidates_old_session_material(): void
    {
        $user = User::factory()->create([
            'email' => 'resetter2@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('old-pass-123'),
            'username' => 'resetter2',
            'remember_token' => Str::random(60),
        ]);
        $oldToken = $user->remember_token;

        $notification = new \Illuminate\Auth\Notifications\ResetPassword('token');
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'resetter2@example.com',
            'password' => 'brand-new-pass-77',
            'password_confirmation' => 'brand-new-pass-77',
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass-77', $user->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('old-pass-123', $user->password));
        $this->assertNotSame($oldToken, (string) $user->remember_token);

        // Old password no longer works (log out of the post-reset session first).
        Auth::logout();
        $this->post(route('login'), [
            'username' => 'resetter2',
            'password' => 'old-pass-123',
        ])->assertSessionHasErrors('username');
    }

    public function test_suspended_user_cannot_reset_password_to_regain_access(): void
    {
        $user = User::factory()->create([
            'email' => 'susp@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('old-pass-123'),
            'account_status' => 'suspended',
        ]);
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'susp@example.com',
            'password' => 'new-pass-999',
            'password_confirmation' => 'new-pass-999',
        ])->assertInvalid('email');

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('old-pass-123', $user->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('new-pass-999', $user->password));
    }

    // ---------------- OTP removal ----------------

    public function test_otp_routes_are_removed(): void
    {
        $this->get('/auth/otp')->assertNotFound();
        $this->post('/auth/otp/verify', ['access_token' => 'x'])->assertNotFound();
    }
}

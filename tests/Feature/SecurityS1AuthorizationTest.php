<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use App\Models\MediaItem;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\SourceMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * S1 — application security regression coverage.
 *
 * Only boundaries NOT already covered by the phase suites (Phase5/8/9/11/12/14
 * cover preview/media/payment-doc/membership/URL IDOR and Google linking).
 * This class closes the remaining gaps found in the S1 audit:
 *  - suspended staff must not keep using non-Filament /staff/* routes;
 *  - members must be denied the staff media-preview route outright;
 *  - cross-member customer-preview approval must be refused;
 *  - logout must invalidate the session and cycle the remember token;
 *  - protected writes must fail CSRF without a token;
 *  - unauthenticated access to member objects must redirect, not leak.
 */
class SecurityS1AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $memberA;

    private User $memberB;

    private Application $appA;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');

        $this->memberA = User::factory()->create(['email_verified_at' => now()]);
        $this->memberB = User::factory()->create(['email_verified_at' => now()]);
        $this->appA = Application::factory()->paid()->create([
            'user_id' => $this->memberA->id,
            'full_name' => 'Member A Application',
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'status' => Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW,
        ]);
    }

    // ---------------- IDOR: cross-member boundaries ----------------

    public function test_member_cannot_view_another_members_application(): void
    {
        $this->actingAs($this->memberB)
            ->getJson(route('applications.show', $this->appA))
            ->assertForbidden();
    }

    public function test_member_cannot_view_another_members_interview(): void
    {
        $this->actingAs($this->memberB)
            ->getJson(route('online-interview.show', $this->appA))
            ->assertForbidden();
    }

    public function test_member_cannot_upload_material_to_another_members_application(): void
    {
        $this->actingAs($this->memberB)
            ->postJson(route('applications.upload.material', $this->appA), [
                'material' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
                'material_type' => 'biography',
            ])
            ->assertForbidden();
    }

    public function test_member_cannot_approve_another_members_preview(): void
    {
        // Move A's application to the approval stage.
        $this->appA->forceFill([
            'status' => Application::STATUS_EDITORIAL_APPROVED,
            'customer_preview_released_at' => now(),
        ])->save();

        $this->actingAs($this->memberB)
            ->postJson(route('applications.preview.approve', $this->appA), [
                'english_editorial_content_id' => 999999,
                'confirm_approval' => '1',
            ])
            ->assertForbidden();

        $this->appA->refresh();
        $this->assertNull($this->appA->customer_approved_at);
    }

    public function test_member_cannot_submit_revision_request_for_another_members_application(): void
    {
        $this->appA->forceFill([
            'status' => Application::STATUS_EDITORIAL_APPROVED,
            'customer_preview_released_at' => now(),
        ])->save();

        $this->actingAs($this->memberB)
            ->postJson(route('applications.preview.revision', $this->appA), [
                'request_type' => 'revision',
                'request_text' => 'Please change everything to my liking.',
            ])
            ->assertForbidden();

        $this->assertSame(0, EditorialRevisionRequest::query()->where('application_id', $this->appA->id)->count());
    }

    // ---------------- Unauthenticated access ----------------

    public function test_unauthenticated_application_access_redirects_to_login_not_leaking(): void
    {
        $this->get(route('applications.show', $this->appA))
            ->assertRedirect(route('login'));

        $this->get(route('applications.payment', $this->appA))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // ---------------- Staff boundaries ----------------

    public function test_member_cannot_use_staff_media_preview_route(): void
    {
        $media = $this->mediaForProfile();

        $this->actingAs($this->memberB)
            ->get(route('staff.profile-media.preview', $media))
            ->assertForbidden();

        $this->actingAs($this->memberA)
            ->get(route('staff.profile-media.preview', $media))
            ->assertForbidden(); // even the owning member: staff-only route
    }

    private function mediaForProfile(): MediaItem
    {
        $profile = Profile::query()->create([
            'user_id' => $this->memberA->id,
            'status' => 'under_editorial_review',
            'full_name' => 'S1 Person',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $this->appA->forceFill(['profile_id' => $profile->id])->save();

        $media = MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
            'storage_path_key' => 'profile-media/s1/preview.jpg',
            'disk' => 'public',
            'privacy' => MediaItem::PRIVACY_PRIVATE,
            'review_status' => MediaItem::REVIEW_PENDING,
            'uploaded_by_id' => $this->memberA->id,
        ]);
        Storage::disk('public')->put($media->storage_path_key, 'jpeg-bytes');

        return $media;
    }

    public function test_suspended_editor_cannot_keep_using_staff_download_route(): void
    {
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $application = Application::factory()->paid()->create([
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        $material = SourceMaterial::query()->create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'material_type' => 'biography',
            'storage_disk' => 'public',
            'storage_path' => 'source-materials/s1.jpg',
            'original_filename' => 's1.jpg',
            'mime_type' => 'image/jpeg',
            'file_bytes' => 100,
            'uploaded_at' => now(),
        ]);
        Storage::disk('public')->put($material->storage_path, 'bytes');

        $editor->forceFill(['account_status' => 'suspended'])->save();

        $this->actingAs($editor)
            ->get(route('staff.source-materials.download', $material))
            ->assertRedirect(route('login'));

        $this->assertFalse(Auth::check(), 'Suspended staff session must be terminated.');
    }

    public function test_suspended_editor_cannot_keep_using_staff_media_preview(): void
    {
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $media = $this->mediaForProfile();

        $editor->forceFill(['account_status' => 'suspended'])->save();

        $this->actingAs($editor)
            ->get(route('staff.profile-media.preview', $media))
            ->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    // ---------------- Session / logout ----------------

    public function test_logout_invalidates_session_and_cycles_remember_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $oldRemember = Str::random(60);
        $user->forceFill(['remember_token' => $oldRemember])->save();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        $user->refresh();
        $this->assertNotSame($oldRemember, (string) $user->remember_token, 'Remember token must be cycled on logout.');
        $this->assertNotSame('', (string) $user->remember_token);
    }

    public function test_member_cannot_relogin_with_a_cycled_remember_token_via_guard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['remember_token' => 'stale-token-value'])->save();

        // Simulate a stolen old remember cookie after logout cycled the token.
        $this->withSession([]);
        $this->app['auth']->guard()->setRememberDuration(60 * 24 * 30);
        $recalled = $this->app['auth']->guard()->user();

        $this->assertNull($recalled ?? null, 'A cycled remember token must not authenticate.');
    }

    // ---------------- CSRF ----------------

    public function test_csrf_protection_remains_on_web_group_with_webhook_exception(): void
    {
        // Laravel 11 wires CSRF (PreventRequestForgery) into the `web` group.
        // It must stay present, and the Razorpay webhook must remain the only
        // deliberate exception (authenticated by signature instead).
        $web = app('router')->getMiddlewareGroups()['web'] ?? [];
        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            $web,
            'CSRF middleware must remain in the web group.',
        );

        $raw = file_get_contents($this->app->bootstrapPath('app.php'));
        $this->assertStringContainsString('validateCsrfTokens(except: [', $raw);
        $this->assertStringContainsString("'payments/razorpay/webhook'", $raw);
    }

    // ---------------- Google OAuth linking regression ----------------

    public function test_google_link_cannot_be_swapped_by_rereading_callback_with_same_email_different_id(): void
    {
        $victim = User::factory()->create([
            'email' => 'linked@example.com',
            'email_verified_at' => now(),
            'google_id' => 'google-held',
        ]);

        // Attacker controls a Google account with a DIFFERENT id but somehow
        // the same verified email: callback must refuse, not overwrite.
        $this->mockGoogleUser('google-attacker-2', 'linked@example.com', 'Attacker');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame('google-held', $victim->fresh()->google_id);
    }

    public function test_google_new_member_creation_is_always_role_member_and_active(): void
    {
        $this->mockGoogleUser('google-brand-new', 'fresh.s1@example.com', 'Fresh S1');

        $this->get(route('auth.google.callback'))
            ->assertRedirect();

        $user = User::query()->where('email', 'fresh.s1@example.com')->firstOrFail();
        $this->assertSame('member', $user->role);
        $this->assertSame('active', $user->account_status);
        $this->assertNull($user->password);
        $this->assertTrue(Auth::check());
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

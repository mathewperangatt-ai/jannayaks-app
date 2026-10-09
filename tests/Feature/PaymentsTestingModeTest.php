<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\PhotoEnhancementRun;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\PhotoEnhancementService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TEMPORARY payments testing mode (JANNAYAKS_PAYMENTS_TESTING_MODE).
 *
 * While active: the payment requirement is suspended for every application —
 * new testers complete the whole workflow (interview, uploads, photo
 * enhancement) WITHOUT any payment record or fake settlement — and payment
 * initiation endpoints are refused server-side.
 *
 * While inactive: the normal payment-required workflow applies unchanged;
 * settled payments and staff waivers remain the only unlock paths.
 */
class PaymentsTestingModeTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicationFor(User $user): Application
    {
        $this->actingAs($user)->post(route('applications.store'), [
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Testing Mode Test',
            'contact_email' => 'testing.mode@example.com',
        ])->assertRedirect();

        return Application::query()->latest('id')->first();
    }

    /* Testing mode ON: the complete workflow is reachable without payment. */
    public function test_testing_mode_lets_new_testers_complete_the_workflow_without_payment(): void
    {
        config(['jannayaks.payments.testing_mode' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        $this->actingAs($user)
            ->get(route('online-interview.show', ['application' => $app->id]))
            ->assertOk();
        $this->actingAs($user)
            ->get(route('applications.upload.show', ['application' => $app->id]))
            ->assertOk();

        // No payment record and no fake settlement anywhere.
        $this->assertSame(0, Payment::query()->count());
        $app->refresh();
        $this->assertSame('payment_pending', (string) $app->status);
        $this->assertSame('pending', (string) $app->payment_status, 'No simulated "paid" state may be recorded.');
        $this->assertNull($app->payment_settled_at);
    }

    /* Testing mode ON: initiation is refused server-side. */
    public function test_payment_initiation_is_refused_during_testing_mode(): void
    {
        config(['jannayaks.payments.testing_mode' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        $html = $this->actingAs($user)
            ->post(route('applications.payment.initiate', ['application' => $app->id]));
        $html->assertRedirect();
        $html->assertSessionHasErrors('payment');

        $this->actingAs($user)
            ->postJson(route('applications.payment.initiate', ['application' => $app->id]))
            ->assertStatus(503)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, Payment::query()->where('application_id', $app->id)->count());
        $this->assertSame('payment_pending', (string) $app->fresh()->status);
    }

    /* Testing mode ON: the payment page explains the suspension (ML default + EN). */
    public function test_payment_page_shows_the_testing_notice(): void
    {
        config(['jannayaks.payments.testing_mode' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        $this->actingAs($user)
            ->get(route('applications.payment', ['application' => $app->id]))
            ->assertOk()
            ->assertSee('ടെസ്റ്റിംഗ് കാലത്ത് ഓൺലൈൻ പേയ്‌മെൻ്റ് താൽക്കാലികമായി ലഭ്യമല്ല');

        $this->actingAs($user)
            ->get(route('applications.payment', ['application' => $app->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Online payments are temporarily unavailable during testing');
    }

    /* Testing mode ON: dashboard labels payment honestly and unlocks the workflow. */
    public function test_dashboard_shows_testing_label_and_unlocked_workflow(): void
    {
        config(['jannayaks.payments.testing_mode' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        $this->actingAs($user)
            ->get(route('applications.show', ['application' => $app->id]))
            ->assertOk()
            ->assertSee('Payments unavailable (testing)')
            ->assertSee('Continue Online Interview')
            ->assertDontSee('Pay to unlock interview');
    }

    /* Testing mode OFF: the normal payment-required workflow is restored. */
    public function test_disabling_testing_mode_restores_payment_required_workflow(): void
    {
        config(['jannayaks.payments.testing_mode' => false]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        $this->actingAs($user)
            ->get(route('online-interview.show', ['application' => $app->id]))
            ->assertRedirect(route('applications.payment', ['application' => $app->id]));

        $this->actingAs($user)
            ->postJson(route('applications.payment.initiate', ['application' => $app->id]))
            ->assertStatus(201);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
    }

    /* Testing mode OFF: settled payments and staff waivers remain the unlock paths. */
    public function test_settled_payments_and_staff_waivers_still_unlock_when_mode_off(): void
    {
        config(['jannayaks.payments.testing_mode' => false]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        $gate = app(ApplicationPaymentStateService::class);
        $this->assertFalse($gate->unlocksInterviewOrUploads($app->fresh()));

        Payment::query()->create([
            'application_id' => $app->id,
            'transaction_reference' => 'TEST-SETTLE-'.uniqid(),
            'gateway' => 'razorpay',
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '3540.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);
        $this->assertTrue($gate->unlocksInterviewOrUploads($app->fresh()));

        // A different tester's unpaid application remains locked (A2 gives
        // each account one active application, so use a fresh account).
        $other = User::factory()->create(['email_verified_at' => now()]);
        $app2 = $this->makeApplicationFor($other);
        $this->assertFalse($gate->unlocksInterviewOrUploads($app2->fresh()));
        // An authorized staff waiver keeps its existing meaning: payment_status
        // 'waived' recorded by an active staff member.
        $app2->forceFill(['waived_by_user_id' => $admin->id, 'payment_status' => 'waived'])->save();
        $this->assertTrue($gate->unlocksInterviewOrUploads($app2->fresh()));
    }

    /* Photo enhancement follows testing mode; the explicit kill switch wins. */
    public function test_photo_enhancement_follows_testing_mode(): void
    {
        $enhancement = app(PhotoEnhancementService::class);

        config(['jannayaks.payments.testing_mode' => false]);
        config(['jannayaks.ai.image_enhancement.enabled' => null]);
        $this->assertFalse($enhancement->enabled(), 'Default (no testing, no explicit env) must stay off.');

        config(['jannayaks.payments.testing_mode' => true]);
        $this->assertTrue($enhancement->enabled(), 'Testing mode enables enhancement for testers.');

        config(['jannayaks.ai.image_enhancement.enabled' => false]);
        $this->assertFalse($enhancement->enabled(), 'Explicit kill switch wins over testing mode.');

        config(['jannayaks.ai.image_enhancement.enabled' => true]);
        config(['jannayaks.payments.testing_mode' => false]);
        $this->assertTrue($enhancement->enabled(), 'Explicit enable wins even with testing mode off.');
    }

    /* Testing mode ON: a tester upload runs the complete enhancement workflow. */
    public function test_tester_upload_produces_an_enhanced_photo_during_testing_mode(): void
    {
        config(['jannayaks.payments.testing_mode' => true]);
        config(['jannayaks.ai.image_enhancement.provider' => 'fake']);
        Storage::fake('public');
        config(['jannayaks.media.public_disk' => 'public']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);
        $profile = Profile::query()->create([
            'user_id' => $user->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Testing Mode Portrait',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        // Link the profile to the application the way the editorial pipeline
        // does, so the upload resolves its package tier.
        $app->forceFill(['profile_id' => $profile->id])->save();

        $item = app(ProfileMediaService::class)->uploadProfilePhoto(
            profile: $profile,
            file: UploadedFile::fake()->image('portrait.jpg', 900, 1200),
            actor: $user,
            altText: 'Tester portrait',
        );
        $this->assertNotNull($item);

        // The enhancement candidate was created and processed by the fake
        // provider (sync queue), and is stored for profile use.
        $this->assertSame(1, PhotoEnhancementRun::query()->count());
        $this->assertTrue(
            \App\Models\MediaItem::query()
                ->where('enhanced_from_media_id', $item->id)
                ->where('media_type', 'profile_photo_enhancement')
                ->exists(),
            'An enhanced candidate photo must exist for the tester upload.'
        );
    }
}

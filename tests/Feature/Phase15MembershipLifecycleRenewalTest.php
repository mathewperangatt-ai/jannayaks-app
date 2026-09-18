<?php

namespace Tests\Feature;

use App\Mail\MembershipRenewalReminderMail;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Membership;
use App\Models\MembershipLifecycleEvent;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\MembershipLifecycleService;
use App\Services\ProfileUrlService;
use App\Services\RazorpayPaymentService;
use App\Services\RazorpayWebhookVerifier;
use App\Services\StaffAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15MembershipLifecycleRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('jannayaks.membership_lifecycle.grace_period_days', 3);
        Config::set('jannayaks.membership_lifecycle.retention_years', 1);
        Config::set('jannayaks.membership_lifecycle.reminder_days_before_expiry', [7]);
        Config::set('jannayaks.membership_lifecycle.reminder_days_after_expiry', [1]);
        // Keep P15 date fixtures stable regardless of production Asia/Kolkata default.
        Config::set('jannayaks.membership_lifecycle.business_timezone', 'UTC');
        Config::set('services.razorpay.enabled', false);
        Config::set('services.razorpay.webhook_secret', 'p15-webhook-secret');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_publish_creates_active_membership_and_profile_stays_public(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Active Leader']);

        $membership = $profile->fresh()->membership;
        $this->assertNotNull($membership);
        $this->assertSame('active', $membership->status);
        $this->assertNotNull($membership->ends_on);
        $this->assertSame(
            $membership->ends_on->copy()->addYear()->toDateString(),
            $membership->retention_until->toDateString(),
        );

        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
        $this->get(route('profiles.public', $profile->slug))->assertOk()->assertSee('Active Leader', false);
        $this->actingAs($member)->get(route('membership.show', $profile))->assertOk()->assertSee('ACTIVE', false);
    }

    public function test_profile_remains_public_during_three_day_grace_and_not_before_boundary(): void
    {
        [$profile] = $this->makePublishedProfile(['display_name' => 'Grace Leader']);
        $membership = $profile->membership;
        $ends = Carbon::parse('2026-01-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'starts_on' => $ends->copy()->subYear()->toDateString(),
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        $lifecycle = app(MembershipLifecycleService::class);

        // Day after expiry (grace day 1) — still public, not deactivated.
        Carbon::setTestNow($ends->copy()->addDay());
        $this->assertTrue($lifecycle->isWithinGracePeriod($membership->fresh()));
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-01-02']);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
        $this->get(route('profiles.public', $profile->slug))->assertOk();

        // Last inclusive grace day (ends_on + 3).
        Carbon::setTestNow($ends->copy()->addDays(3));
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-01-04']);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));

        // Day after grace — deactivate.
        Carbon::setTestNow($ends->copy()->addDays(4));
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-01-05']);
        $membership = $membership->fresh();
        $profile = $profile->fresh();
        $this->assertSame('lapsed', $membership->status);
        $this->assertSame('inactive', $profile->status);
        $this->assertNotNull($profile->unpublished_at);
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->get(route('profiles.public', $profile->slug))->assertNotFound();
    }

    public function test_deactivated_profile_absent_from_gallery_search_and_media(): void
    {
        Storage::fake('public');
        [$profile] = $this->makePublishedProfile([
            'display_name' => 'Lapsed Searchable',
            'english_body' => 'Unique lapse biography text.',
        ]);
        $path = 'photos/lapsed-'.$profile->id.'.jpg';
        Storage::disk('public')->put($path, 'img');
        $media = MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => 'profile_photo',
            'storage_path_key' => $path,
            'disk' => 'public',
            'caption' => null,
            'alt_text' => 'Portrait',
            'display_order' => 1,
            'is_primary' => true,
            'privacy' => 'public',
            'review_status' => 'approved',
            'mime_type' => 'image/jpeg',
        ]);
        ProfileExternalLink::query()->create([
            'profile_id' => $profile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => 'Approved video',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'status' => ProfileExternalLink::STATUS_APPROVED,
            'is_publicly_active' => true,
        ]);

        $this->forceLapsedPastGrace($profile);

        $this->get('/gallery')->assertOk()->assertDontSee('Lapsed Searchable', false);
        $this->get('/search?q=Lapsed')->assertOk()->assertDontSee('Lapsed Searchable', false);
        $this->get(route('profiles.public', $profile->slug))->assertNotFound();
        $this->get(route('profiles.public.photo', [$profile, $media]))->assertNotFound();

        // Retention: data remains.
        $this->assertDatabaseHas('profiles', ['id' => $profile->id, 'slug' => $profile->slug]);
        $this->assertDatabaseHas('media_items', ['id' => $media->id]);
        $this->assertDatabaseHas('editorial_contents', ['profile_id' => $profile->id]);
        $this->assertNotNull($profile->fresh()->membership);
    }

    public function test_retention_uses_expiry_not_deactivation_date(): void
    {
        [$profile] = $this->makePublishedProfile();
        $membership = $profile->membership;
        $ends = Carbon::parse('2025-06-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'ends_on' => $ends->toDateString(),
            'retention_until' => null,
            'status' => 'active',
        ])->save();

        // Cron runs late (grace ended days ago).
        Carbon::setTestNow($ends->copy()->addDays(10));
        app(MembershipLifecycleService::class)->processMembershipLifecycle($membership->id, Carbon::parse('2025-06-11'));

        $membership = $membership->fresh();
        $this->assertSame('lapsed', $membership->status);
        $this->assertSame('2026-06-01', $membership->retention_until->toDateString());
    }

    public function test_renewal_before_expiry_extends_from_ends_on_without_duplicate_profile(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Early Renew']);
        $membership = $profile->membership;
        $ends = Carbon::parse('2026-12-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'starts_on' => '2025-12-01',
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        Carbon::setTestNow($ends->copy()->subMonths(2));
        $payment = $this->settleMembershipRenewal($membership, $member);

        $membership = $membership->fresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame('2027-12-01', $membership->ends_on->toDateString());
        $this->assertSame(1, Membership::query()->where('profile_id', $profile->id)->count());
        $this->assertSame(1, Profile::query()->where('user_id', $member->id)->count());
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
        $this->assertSame($payment->id, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_RENEWED)
            ->value('meta')['payment_id'] ?? null);
    }

    public function test_renewal_during_grace_keeps_public_and_extends_from_ends_on(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Grace Renew']);
        $membership = $profile->membership;
        $ends = Carbon::parse('2026-03-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'starts_on' => '2025-03-01',
            'ends_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        Carbon::setTestNow($ends->copy()->addDays(2));
        $this->assertTrue(app(MembershipLifecycleService::class)->isWithinGracePeriod($membership->fresh()));
        $this->settleMembershipRenewal($membership, $member);

        $profile = $profile->fresh();
        $membership = $membership->fresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame('2027-03-01', $membership->ends_on->toDateString());
        $this->assertSame('published', $profile->status);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->get(route('profiles.public', $profile->slug))->assertOk()->assertSee('Grace Renew', false);
    }

    public function test_renewal_after_deactivation_reactivates_same_profile_url_media(): void
    {
        Storage::fake('public');
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Comeback Leader']);
        $slug = $profile->slug;
        $path = 'photos/comeback-'.$profile->id.'.jpg';
        Storage::disk('public')->put($path, 'img');
        MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => 'profile_photo',
            'storage_path_key' => $path,
            'disk' => 'public',
            'caption' => null,
            'alt_text' => 'Portrait',
            'display_order' => 1,
            'is_primary' => true,
            'privacy' => 'public',
            'review_status' => 'approved',
            'mime_type' => 'image/jpeg',
        ]);

        $this->forceLapsedPastGrace($profile);
        $profile = $profile->fresh();
        $this->assertSame('inactive', $profile->status);
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));

        Carbon::setTestNow(now()->addDays(30));
        $this->settleMembershipRenewal($profile->membership, $member);

        $profile = $profile->fresh();
        $membership = $profile->membership->fresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame('published', $profile->status);
        $this->assertNull($profile->unpublished_at);
        $this->assertSame($slug, $profile->slug);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->get(route('profiles.public', $slug))->assertOk()->assertSee('Comeback Leader', false);
        $this->assertSame(1, Profile::query()->where('user_id', $member->id)->count());
        $this->assertDatabaseHas('media_items', [
            'mediable_id' => $profile->id,
            'review_status' => 'approved',
        ]);
    }

    public function test_unpaid_forged_wrong_amount_currency_cancelled_do_not_reactivate(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Secure Renew']);
        $this->forceLapsedPastGrace($profile);
        $membership = $profile->fresh()->membership;

        // Unpaid initiate only.
        $pending = app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($membership, $member);
        $this->assertFalse($pending->isPaidOrBetter());
        $this->assertSame('inactive', $profile->fresh()->status);

        // Wrong amount webhook.
        $pending->update(['razorpay_link_id' => 'link_p15_wrongamt']);
        $this->postSignedMembershipWebhook($pending, amountPaise: 100)->assertStatus(409);
        $this->assertSame('inactive', $profile->fresh()->status);
        $this->assertSame('lapsed', $membership->fresh()->status);

        // Wrong currency.
        $pending2 = app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($membership->fresh(), $member);
        $pending2->update(['razorpay_link_id' => 'link_p15_wrongcur']);
        $this->postSignedMembershipWebhook($pending2, amountPaise: $pending2->totalPaise(), currency: 'USD')->assertStatus(409);

        // Cancelled / superseded attempt cannot settle.
        $pending3 = app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($membership->fresh(), $member);
        $pending3->update(['razorpay_link_id' => 'link_p15_cancel']);
        app(RazorpayPaymentService::class)->supersedeAttempt($pending3, 'cancelled in test');
        $this->postSignedMembershipWebhook($pending3->fresh(), amountPaise: $pending3->totalPaise())
            ->assertStatus(409);
        $this->assertSame('inactive', $profile->fresh()->status);

        // Forged signature.
        $pending4 = app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($membership->fresh(), $member);
        $pending4->update(['razorpay_link_id' => 'link_p15_forged']);
        $payload = $this->membershipWebhookPayload($pending4, $pending4->totalPaise(), 'INR', 'evt_forged');
        $this->postJson(route('payments.razorpay.webhook'), $payload, [
            RazorpayWebhookVerifier::SIGNATURE_HEADER => 'deadbeef',
            'Content-Type' => 'application/json',
        ])->assertStatus(403);
        $this->assertSame('inactive', $profile->fresh()->status);
    }

    public function test_lifecycle_command_idempotent_and_catch_up_and_no_duplicate_reminders(): void
    {
        Mail::fake();
        [$profile] = $this->makePublishedProfile(['display_name' => 'Cron Leader']);
        $member = $profile->user;
        $member->forceFill(['email' => 'cron-leader@example.com'])->save();

        $membership = $profile->membership;
        $ends = Carbon::parse('2026-04-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        // Reminder 7 days before.
        Carbon::setTestNow($ends->copy()->subDays(7));
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-03-25']);
        Mail::assertSent(MembershipRenewalReminderMail::class, 1);
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-03-25']);
        Mail::assertSent(MembershipRenewalReminderMail::class, 1);
        $this->assertSame(1, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_REMINDER_BEFORE)
            ->count());

        // Missed cron: jump past grace; catch-up deactivates once.
        Carbon::setTestNow($ends->copy()->addDays(10));
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-04-11']);
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-04-11']);
        $this->assertSame('lapsed', $membership->fresh()->status);
        $this->assertSame(1, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_DEACTIVATED)
            ->count());
        $this->assertSame(1, StaffActionLog::query()->where('action', 'membership.deactivated')->count());
    }

    public function test_email_failure_does_not_corrupt_membership_and_allows_retry_without_duplicate(): void
    {
        [$profile] = $this->makePublishedProfile();
        $profile->user->forceFill(['email' => 'fail-mail@example.com'])->save();
        $membership = $profile->membership;
        $ends = Carbon::parse('2026-05-01', 'UTC')->startOfDay();
        $membership->forceFill([
            'ends_on' => $ends->toDateString(),
            'status' => 'active',
            'retention_until' => $ends->copy()->addYear()->toDateString(),
        ])->save();

        Carbon::setTestNow($ends->copy()->subDays(7));

        $failing = new class(app(StaffAuditLogger::class)) extends MembershipLifecycleService
        {
            public int $attempts = 0;

            protected function deliverRenewalReminderMail(
                string $email,
                Membership $membership,
                string $eventType,
                int $offsetDays,
            ): void {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new \RuntimeException('SMTP down');
                }

                parent::deliverRenewalReminderMail($email, $membership, $eventType, $offsetDays);
            }
        };
        $this->app->instance(MembershipLifecycleService::class, $failing);

        Mail::fake();
        $failing->processMembershipLifecycle($membership->id, $ends->copy()->subDays(7));
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame(0, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_REMINDER_BEFORE)
            ->count());
        Mail::assertNothingSent();

        $failing->processMembershipLifecycle($membership->id, $ends->copy()->subDays(7));
        Mail::assertSent(MembershipRenewalReminderMail::class, 1);
        $this->assertSame(1, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_REMINDER_BEFORE)
            ->count());

        $failing->processMembershipLifecycle($membership->id, $ends->copy()->subDays(7));
        Mail::assertSent(MembershipRenewalReminderMail::class, 1);
        $this->assertSame(2, $failing->attempts);
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_post_deactivation_renewal_starts_new_period_from_successful_renewal_date(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Restart Period']);
        $this->forceLapsedPastGrace($profile);
        $slug = $profile->fresh()->slug;

        // Settlement day after deactivation (within retention).
        Carbon::setTestNow(Carbon::parse('2026-02-15', 'UTC')->startOfDay());
        $this->settleMembershipRenewal($profile->membership, $member);

        $membership = $profile->fresh()->membership->fresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame('2026-02-15', $membership->starts_on->toDateString());
        $this->assertSame('2027-02-15', $membership->ends_on->toDateString());
        $this->assertSame('2028-02-15', $membership->retention_until->toDateString());
        $this->assertSame($slug, $profile->fresh()->slug);
        $this->assertSame(1, Profile::query()->where('user_id', $member->id)->count());
    }

    public function test_lifecycle_business_timezone_is_independent_of_app_utc(): void
    {
        Config::set('app.timezone', 'UTC');
        Config::set('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata');

        $lifecycle = app(MembershipLifecycleService::class);
        $this->assertSame('Asia/Kolkata', $lifecycle->businessTimezone());

        // 2026-01-05 00:30 IST is still 2026-01-04 UTC — business "today" must be IST calendar day.
        Carbon::setTestNow(Carbon::parse('2026-01-04 19:00:00', 'UTC'));
        $this->assertSame('2026-01-05', $lifecycle->today()->toDateString());
    }

    public function test_settled_renewal_wins_over_stale_deactivation_decision(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Race Leader']);
        $membership = $profile->membership;
        $ends = Carbon::parse('2026-07-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'ends_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        // Past grace — eligible for deactivation.
        Carbon::setTestNow($ends->copy()->addDays(5));

        // Renewal settles first.
        $this->settleMembershipRenewal($membership, $member);
        $this->assertSame('active', $membership->fresh()->status);

        // Cron runs with stale "past grace" clock but re-checks locked membership.
        $this->artisan('membership:process-lifecycle', ['--date' => '2026-07-06']);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('published', $profile->fresh()->status);
        $this->assertSame(0, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_DEACTIVATED)
            ->count());
    }

    public function test_user_cannot_renew_another_users_membership(): void
    {
        [$profileA] = $this->makePublishedProfile(['display_name' => 'Owner A']);
        [$profileB, $memberB] = $this->makePublishedProfile(['display_name' => 'Owner B']);

        $this->actingAs($memberB)->get(route('membership.show', $profileA))->assertForbidden();
        $this->actingAs($memberB)->post(route('membership.renew', $profileA))->assertForbidden();

        $this->expectException(\InvalidArgumentException::class);
        app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($profileA->membership, $memberB);
    }

    public function test_admin_test_demo_publish_still_creates_membership_without_payment_waiver_authority_for_renewal(): void
    {
        [$profile] = $this->makePublishedProfile(['display_name' => 'Demo Pub']);
        $this->assertNotNull($profile->membership);
        // Renewal still requires ITEM_MEMBERSHIP settled payment — initiating alone does not reactivate.
        $this->forceLapsedPastGrace($profile);
        $member = $profile->user;
        $payment = app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($profile->membership, $member);
        $this->assertFalse($payment->isPaidOrBetter());
        $this->assertSame('inactive', $profile->fresh()->status);
    }

    public function test_suspended_profile_is_not_reactivated_by_ordinary_renewal(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Suspended One']);
        $this->forceLapsedPastGrace($profile);
        $profile->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
            'unpublished_at' => now(),
        ])->save();

        $this->settleMembershipRenewal($profile->membership, $member);
        $profile = $profile->fresh();
        $this->assertSame('suspended', $profile->status);
        $this->assertSame('active', $profile->membership->status);
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{0: Profile, 1: User, 2: Application}
     */
    private function makePublishedProfile(array $opts = []): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
        $member = User::factory()->create([
            'email' => $opts['email'] ?? ('member-'.uniqid().'@example.com'),
            'email_verified_at' => now(),
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $opts['display_name'] ?? 'Leader '.uniqid(),
            'display_name' => $opts['display_name'] ?? 'Leader '.uniqid(),
            'profession' => $opts['profession'] ?? 'Public servant',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'package_tier' => $opts['tier'] ?? 'accomplished',
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $profile->display_name,
            'body' => $opts['english_body'] ?? 'Approved English biography.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $profile = Profile::query()->findOrFail($published->profile_id);

        return [$profile->fresh(), $member->fresh(), $published->fresh()];
    }

    private function forceLapsedPastGrace(Profile $profile): void
    {
        $membership = $profile->membership;
        $ends = Carbon::parse('2026-01-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'starts_on' => $ends->copy()->subYear()->toDateString(),
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        Carbon::setTestNow($ends->copy()->addDays(5));
        app(MembershipLifecycleService::class)->processMembershipLifecycle($membership->id, $ends->copy()->addDays(5));
    }

    private function settleMembershipRenewal(Membership $membership, User $actor): Payment
    {
        $payment = app(RazorpayPaymentService::class)->createMembershipRenewalPaymentLink($membership->fresh(), $actor);
        $payment->update(['razorpay_link_id' => 'link_p15_'.$payment->id]);
        $res = $this->postSignedMembershipWebhook($payment->fresh(), amountPaise: $payment->totalPaise());
        $res->assertOk()->assertJson(['ok' => true, 'paid' => true]);

        return $payment->fresh();
    }

    private function postSignedMembershipWebhook(
        Payment $payment,
        ?int $amountPaise = null,
        string $currency = 'INR',
        ?string $eventId = null,
    ) {
        $amountPaise ??= $payment->totalPaise();
        $eventId ??= 'evt_p15_'.$payment->id.'_'.uniqid();
        $payload = $this->membershipWebhookPayload($payment, $amountPaise, $currency, $eventId);
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = (new RazorpayWebhookVerifier)->computeSignature($raw, 'p15-webhook-secret');

        return $this->call(
            'POST',
            route('payments.razorpay.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', RazorpayWebhookVerifier::SIGNATURE_HEADER)) => $signature,
            ],
            $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipWebhookPayload(Payment $payment, int $amountPaise, string $currency, string $eventId): array
    {
        return [
            'event' => 'payment.captured',
            'event_id' => $eventId,
            'created_at' => Carbon::now()->getTimestamp(),
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_p15_'.$payment->id,
                        'order_id' => null,
                        'amount' => $amountPaise,
                        'currency' => $currency,
                        'notes' => [
                            'payment_id' => (string) $payment->id,
                            'membership_id' => (string) $payment->membership_id,
                            'item_type' => Payment::ITEM_MEMBERSHIP,
                        ],
                    ],
                ],
                'payment_link' => [
                    'entity' => [
                        'id' => (string) $payment->razorpay_link_id,
                        'reference_id' => (string) $payment->transaction_reference,
                        'amount' => $amountPaise,
                        'currency' => $currency,
                    ],
                ],
            ],
        ];
    }
}

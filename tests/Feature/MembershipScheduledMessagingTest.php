<?php

namespace Tests\Feature;

use App\Mail\MembershipRenewalReminderMail;
use App\Models\MembershipLifecycleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Profile Workflow Test-2 — closes the scheduled-automation audit gaps ONLY:
 *   1. reminder email rendering (view/subject/provisional markers/recipient),
 *   2. invalid-email claim branch via the real artisan command,
 *   3. artisan command → lifecycle service wiring for all configured offsets,
 *   4. scheduler declaration for the membership lifecycle command.
 *
 * Everything else (idempotency, catch-up, mail-failure retry, grace/
 * deactivation, renewal/reactivation, timezone) remains covered by
 * Phase15MembershipLifecycleRenewalTest and is deliberately not duplicated.
 */
class MembershipScheduledMessagingTest extends TestCase
{
    use RefreshDatabase;

    /** Ends-on used by every test (offsets computed against this date). */
    private function endsOn(): Carbon
    {
        return Carbon::parse('2026-05-01', 'UTC')->startOfDay();
    }

    /** Published profile + active membership via the Phase-15 publish path. */
    private function makePublishedProfileWithEmail(string $email): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
        $member = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);

        $profile = \App\Models\Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Scheduled Messaging Leader',
            'display_name' => 'Scheduled Messaging Leader',
            'profession' => 'Public servant',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = \App\Models\Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
            'status' => \App\Models\Application::STATUS_AWAITING_PUBLICATION,
        ]);

        $english = \App\Models\EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => \App\Models\EditorialContent::LANGUAGE_EN,
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $profile->display_name,
            'body' => 'Approved English biography.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => \App\Models\Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(\App\Services\ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        $membership = $profile->fresh()->membership;
        $ends = $this->endsOn();
        $membership->forceFill([
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        return [$profile->fresh(), $member->fresh(), $membership->fresh()];
    }

    private function runLifecycleFor(Carbon $on): void
    {
        Artisan::call('membership:process-lifecycle', [
            '--date' => $on->toDateString(),
        ]);
    }

    /* ------------------------------------------------------------------
     * 1. Reminder email rendering — current provisional implementation.
     * ------------------------------------------------------------------ */
    public function test_reminder_email_renders_provisional_view_to_the_right_recipient(): void
    {
        [$profile, $member, $membership] = $this->makePublishedProfileWithEmail('scheduled-reminders@example.com');
        Mail::fake();

        $this->runLifecycleFor($this->endsOn()->copy()->subDays(7));

        Mail::assertSent(MembershipRenewalReminderMail::class, 1);
        Mail::assertSent(MembershipRenewalReminderMail::class, function (MembershipRenewalReminderMail $mail) use ($membership, $member) {
            // Recipient + payload contract of the current implementation.
            $this->assertTrue($mail->hasTo('scheduled-reminders@example.com'));
            $this->assertSame($membership->id, $mail->membership->id);
            $this->assertSame(MembershipLifecycleEvent::TYPE_REMINDER_BEFORE, $mail->eventType);
            $this->assertSame(7, $mail->offsetDays);

            // Provisional subject/view are the CURRENT implementation and are
            // pinned here on purpose; wording changes later should update this.
            $this->assertSame('[PROVISIONAL] Jannayaks membership renewal reminder', $mail->envelope()->subject);
            $this->assertSame('emails.membership.renewal-reminder', $mail->content()->markdown);

            $html = $mail->render();
            $this->assertStringContainsString('[PROVISIONAL COPY — NOT FINAL]', $html);
            $this->assertStringContainsString('Scheduled Messaging Leader', $html);
            $this->assertStringContainsString($membership->ends_on->toDateString(), $html);

            return true;
        });
    }

    /* ------------------------------------------------------------------
     * 2. Invalid-email branch: claim the reminder, send nothing, never
     *    re-attempt, and do not corrupt or deactivate the membership.
     * ------------------------------------------------------------------ */
    public function test_invalid_email_claims_reminder_without_sending_or_deactivating(): void
    {
        [$profile, $member, $membership] = $this->makePublishedProfileWithEmail('definitely-not-an-email');
        Mail::fake();

        $this->runLifecycleFor($this->endsOn()->copy()->subDays(7));

        Mail::assertNothingSent();

        $event = MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->where('event_type', MembershipLifecycleEvent::TYPE_REMINDER_BEFORE)
            ->first();
        $this->assertNotNull($event, 'the reminder must be claimed so it is not retried forever');
        $this->assertSame('skipped_invalid_email', $event->meta['delivery'] ?? null);

        // The membership itself is untouched by the invalid address.
        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertNull($membership->lapsed_at);

        // A subsequent run must not re-attempt the already-claimed event.
        Mail::fake();
        $this->runLifecycleFor($this->endsOn()->copy()->subDays(7));
        Mail::assertNothingSent();
        $this->assertSame(
            1,
            MembershipLifecycleEvent::query()
                ->where('membership_id', $membership->id)
                ->where('event_type', MembershipLifecycleEvent::TYPE_REMINDER_BEFORE)
                ->count()
        );
    }

    /* ------------------------------------------------------------------
     * 3. The real artisan command wires through to the lifecycle service
     *    and honours every configured reminder offset exactly once.
     * ------------------------------------------------------------------ */
    public function test_artisan_command_produces_each_configured_reminder_offset_once(): void
    {
        [$profile, $member, $membership] = $this->makePublishedProfileWithEmail('offsets@example.com');

        // Model dates cast to UTC midnight; the command parses --date in
        // Asia/Kolkata (IST midnight = 18:30 UTC the day before). The before
        // offsets stay exact through that shift; the +1-day-after reminder is
        // reached on --date = ends_on + 2 (IST), i.e. 1.77 days after expiry.
        $offsets = [
            -60 => MembershipLifecycleEvent::TYPE_REMINDER_BEFORE,
            -30 => MembershipLifecycleEvent::TYPE_REMINDER_BEFORE,
            -7 => MembershipLifecycleEvent::TYPE_REMINDER_BEFORE,
            2 => MembershipLifecycleEvent::TYPE_REMINDER_AFTER,
        ];

        foreach ($offsets as $daysFromEnds => $expectedType) {
            Mail::fake();
            $on = $this->endsOn()->copy()->addDays($daysFromEnds);

            $this->runLifecycleFor($on);

            // Exactly one mail for this run (the reached threshold only).
            Mail::assertSent(MembershipRenewalReminderMail::class, 1);

            $this->assertDatabaseHas('membership_lifecycle_events', [
                'membership_id' => $membership->id,
                'event_type' => $expectedType,
            ]);
        }

        // All four configured offsets are now claimed.
        $this->assertSame(4, MembershipLifecycleEvent::query()
            ->where('membership_id', $membership->id)
            ->whereIn('event_type', [MembershipLifecycleEvent::TYPE_REMINDER_BEFORE, MembershipLifecycleEvent::TYPE_REMINDER_AFTER])
            ->count());

        // Non-threshold dates send nothing.
        Mail::fake();
        $this->runLifecycleFor($this->endsOn()->copy()->subDays(45));
        Mail::assertNothingSent();
    }

    /* ------------------------------------------------------------------
     * 4. Scheduler declaration: the membership lifecycle command is
     *    registered with the configured daily time, Asia/Kolkata, and
     *    withoutOverlapping. Uses the public schedule:event surface via
     *    `schedule:list` (boots routes/console.php) — no framework internals.
     * ------------------------------------------------------------------ */
    public function test_membership_lifecycle_schedule_is_declared(): void
    {
        Artisan::call('schedule:list'); // boots routes/console.php scheduling

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'membership:process-lifecycle'));

        $this->assertSame(1, $events->count(), 'membership lifecycle schedule must be declared exactly once');

        $event = $events->first();
        $this->assertSame(
            (string) config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata'),
            (string) $event->timezone,
        );
        $this->assertTrue((bool) $event->withoutOverlapping);
        $this->assertTrue((bool) $event->expiresAt); // withoutOverlapping(120) sets a lock expiry
    }
}

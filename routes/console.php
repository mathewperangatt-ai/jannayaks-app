<?php

use App\Console\Commands\FailStaleAiEditorialRunsCommand;
use App\Console\Commands\ProcessMembershipLifecycleCommand;
use App\Console\Commands\ReconcilePaymentCapturesCommand;
use App\Console\Commands\VerifyProfileIntegrityCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Membership lifecycle (Phase 15).
| Application registers the schedule here. Production still requires an external
| system cron / platform scheduler to invoke `php artisan schedule:run` regularly.
| Railway (or other) host-side cron is NOT assumed merely because this exists.
*/
$lifecycleAt = (string) config('jannayaks.membership_lifecycle.scheduler.daily_at', '01:15');
$lifecycleTz = (string) config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata');

Schedule::command(ProcessMembershipLifecycleCommand::class)
    ->dailyAt($lifecycleAt)
    ->timezone($lifecycleTz !== '' ? $lifecycleTz : 'Asia/Kolkata')
    ->withoutOverlapping(120)
    ->name('membership-process-lifecycle');

/*
| AI editorial generation runs synchronously in the HTTP request. If the process
| dies mid-run (proxy timeout, OOM, redeploy), the ai_editorial_runs row stays
| "running" forever and the per-application partial unique index blocks every
| retry. This sweeper releases those wedges; runs on the production scheduler
| service (see docs/membership-lifecycle-scheduler.md).
*/
Schedule::command(FailStaleAiEditorialRunsCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->name('fail-stale-ai-editorial-runs');

/*
| Recover a captured Razorpay payment whose webhook retries may have stopped.
| Reads the gateway outside any row lock. Does not cancel open links.
| Runs on the existing scheduler service (php artisan schedule:work).
*/
Schedule::command(ReconcilePaymentCapturesCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->name('reconcile-payment-captures');

/*
| Published Profile Integrity Monitor (Wave 2C) — REPORT-ONLY watchdog.
| Compares each published profile's live public state against its known-good
| snapshot; records incidents for unexplained differences. Never suspends or
| modifies content in this wave. Chunked and circuit-broken (see
| VerifyProfileIntegrityCommand); runs on the existing scheduler service.
*/
Schedule::command(VerifyProfileIntegrityCommand::class)
    ->twiceDaily(0, 12)
    ->withoutOverlapping(60)
    ->name('verify-profile-integrity');

/*
| TEMPORARY weekly contact-numbers export (approved while phone storage is
| being minimised): exports paid applications' contact/WhatsApp numbers to
| the owner by CSV email, then removes them from the live database. See
| ExportContactNumbersCommand for eligibility and safety rules.
*/
Schedule::command('jannayaks:export-contact-numbers')
    ->weeklyOn(1, '9:00')
    ->timezone(config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata'))
    ->withoutOverlapping(30)
    ->name('export-contact-numbers');

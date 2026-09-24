<?php

use App\Console\Commands\FailStaleAiEditorialRunsCommand;
use App\Console\Commands\ProcessMembershipLifecycleCommand;
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

<?php

namespace App\Console\Commands;

use App\Services\MembershipLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessMembershipLifecycleCommand extends Command
{
    protected $signature = 'membership:process-lifecycle
                            {--date= : Optional YYYY-MM-DD in membership business timezone for catch-up / tests}';

    protected $description = 'Process membership grace-period deactivations and provisional renewal reminders (idempotent).';

    public function handle(MembershipLifecycleService $lifecycle): int
    {
        $on = null;
        $date = $this->option('date');
        if (is_string($date) && $date !== '') {
            $on = Carbon::parse($date, $lifecycle->businessTimezone())->startOfDay();
        }

        $result = $lifecycle->processDueLifecycle($on);

        $this->info(sprintf(
            'Membership lifecycle: deactivated=%d reminders=%d skipped=%d',
            $result['deactivated'],
            $result['reminders'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}

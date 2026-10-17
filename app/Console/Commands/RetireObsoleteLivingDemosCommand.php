<?php

namespace App\Console\Commands;

use App\Services\ObsoleteLivingDemoRetirementService;
use App\Support\ObsoleteLivingDemos;
use Illuminate\Console\Command;

class RetireObsoleteLivingDemosCommand extends Command
{
    protected $signature = 'jannayaks:retire-obsolete-living-demos
                            {--dry-run : Report matches without changing status}';

    protected $description = 'Idempotently retire the seven obsolete short Recognised/Acclaimed living demos from public listings (demo-domain identities only).';

    public function handle(ObsoleteLivingDemoRetirementService $retirement): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun
            ? 'Dry run: no profile statuses will be changed.'
            : 'Retiring obsolete short living demos (exact email + name match required).');

        $this->table(
            ['Email', 'Full name'],
            array_map(static fn (array $t): array => [$t['email'], $t['full_name']], ObsoleteLivingDemos::targets()),
        );

        $result = $retirement->retire($dryRun);

        foreach ($result['retired'] as $row) {
            $this->line(sprintf(
                '%s profile #%d (%s) — previous status %s',
                $dryRun ? 'Would retire' : 'Retired',
                $row['profile_id'],
                $row['full_name'],
                $row['previous_status'],
            ));
        }
        foreach ($result['skipped'] as $row) {
            $this->warn(sprintf('Skipped %s: %s', $row['full_name'], $row['reason']));
        }
        foreach ($result['missing'] as $row) {
            $this->warn(sprintf('Missing %s: %s', $row['full_name'], $row['reason']));
        }

        $retained = $retirement->retainedLivingDemoProfiles();
        $this->info('Still-published living demos under demo domain: '.$retained->count());
        foreach ($retained as $profile) {
            $this->line(' - '.$profile->full_name.' ('.$profile->slug.')');
        }

        return self::SUCCESS;
    }
}

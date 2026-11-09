<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\IntakeExpiryService;
use App\Support\InterviewSubmissionDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Day-11 erasure of incomplete Online Interview applications.
 *
 * Day 1 is the date the customer first started the questionnaire; the
 * customer may submit through the end of Day 10 (Asia/Kolkata calendar days,
 * InterviewSubmissionDeadline is the single date authority). From Day 11 an
 * unsubmitted application is erased with its working records. The customer
 * account, payments and financial references, consent evidence and audit logs
 * are retained.
 *
 * Dry run is the default: without --execute nothing is modified. --date
 * evaluates the boundary as of a specific IST date (catch-up/testing).
 * --retry-files reconciles physical files that failed to delete earlier.
 */
class ProcessIntakeExpiryCommand extends Command
{
    protected $signature = 'jannayaks:process-intake-expiry
        {--execute : Perform the erasure. Without this option the command only reports (dry run).}
        {--date= : Evaluate eligibility as of this Asia/Kolkata date (Y-m-d). Defaults to now.}
        {--retry-files : Retry physical file deletions recorded as pending by earlier runs.}';

    protected $description = 'Erase incomplete applications whose ten-day questionnaire period expired on Day 11 (audit-logged, financial and consent records retained).';

    public function handle(IntakeExpiryService $expiry): int
    {
        if ($this->option('retry-files') !== null && $this->option('retry-files') !== false) {
            return $this->retryFiles($expiry);
        }

        $asOf = $this->asOf();

        /** @var Collection<int, Application> $eligible */
        $eligible = $expiry->eligibleApplications($asOf);

        if ($eligible->isEmpty()) {
            $this->info('No incomplete applications are due for erasure as of '.$this->asOfLabel($asOf).'.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d incomplete application(s) due for erasure as of %s:',
            $eligible->count(),
            $this->asOfLabel($asOf),
        ));

        foreach ($eligible as $application) {
            $scope = $expiry->describe($application, $asOf);
            $this->line(sprintf(
                '  #%d user #%d %s | Day 1 %s | submitted %s | payment %s | answers %d | materials %d | media %d | editorial %d | files %d',
                $scope['application_id'],
                $scope['user_id'] ?? 0,
                $scope['full_name'],
                $scope['first_day'],
                $scope['last_submission_day'],
                $scope['payment_status'],
                $scope['interview_answers'],
                $scope['source_materials'],
                $scope['media_items'],
                $scope['editorial_versions'],
                count($scope['files']),
            ));
        }

        if (! $this->option('execute')) {
            $this->warn('DRY RUN — nothing was modified. Re-run with --execute to erase the applications above.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;
        $pendingFileApplications = 0;

        foreach ($eligible as $application) {
            $result = $expiry->expire($application, $asOf);

            if ($result['deleted'] !== true) {
                $skipped++;
                $this->warn(sprintf('  #%d skipped (%s).', $application->id, $result['reason']));

                continue;
            }

            $deleted++;
            $failedFiles = $expiry->deleteManifestFiles($result['manifest']);

            if ($failedFiles !== []) {
                $expiry->recordPendingFiles($application->id, $failedFiles);
                $pendingFileApplications++;
                $this->error(sprintf(
                    '  #%d erased, but %d file(s) could not be deleted and are recorded for reconciliation (--retry-files).',
                    $application->id,
                    count($failedFiles),
                ));

                continue;
            }

            $expiry->markFilesCleared($application->id, count($result['manifest']['files'] ?? []));
            $this->line(sprintf('  #%d erased.', $application->id));
        }

        $this->info(sprintf('Erased %d, skipped %d, pending-file applications %d.', $deleted, $skipped, $pendingFileApplications));

        return $pendingFileApplications > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function retryFiles(IntakeExpiryService $expiry): int
    {
        $result = $expiry->retryPendingFiles();

        $this->info(sprintf('Cleared %d pending-file record(s).', $result['cleared']));

        foreach ($result['failed'] as $file) {
            $this->error(sprintf('  Still failing: [%s] %s', $file['disk'], $file['path']));
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function asOf(): ?CarbonImmutable
    {
        $date = trim((string) $this->option('date'));

        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date, InterviewSubmissionDeadline::timezone())->startOfDay();
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('--date must be a valid date in Y-m-d format.');
        }
    }

    private function asOfLabel(?CarbonImmutable $asOf): string
    {
        return $asOf === null
            ? 'now ('.CarbonImmutable::now(InterviewSubmissionDeadline::timezone())->toDateString().' Asia/Kolkata)'
            : $asOf->toDateString().' Asia/Kolkata';
    }
}

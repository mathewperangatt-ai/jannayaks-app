<?php

namespace App\Services;

use App\Mail\ProfileIntegrityAlertMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Founder email after a report-only integrity watchdog run.
 * Never throws out of notify() — delivery failure is logged only.
 *
 * Living profiles listed on /demo-profiles are still scanned and still
 * create incidents; they are omitted from this founder email so customer
 * findings stay visible. Matching uses the same query as DemoProfilesController.
 */
class ProfileIntegrityAlertNotifier
{
    public function __construct(
        private PublicProfileSearchService $profileSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array{profile_id: int, slug: string|null, type: string, detection_reason: string, newly_created: bool}>  $alertFindings
     */
    public function notifyIfNeeded(array $summary, array $alertFindings): void
    {
        $demoProfileIds = $this->livingDemoProfileIdsListedOnDemoPage();

        $customerFindings = array_values(array_filter(
            $alertFindings,
            static fn (array $row): bool => ! in_array((int) $row['profile_id'], $demoProfileIds, true),
        ));

        $customerNewIncidents = count(array_filter(
            $customerFindings,
            static fn (array $row): bool => ! empty($row['newly_created']),
        ));

        $massMismatch = (bool) ($summary['mass_mismatch_alert'] ?? false);

        if ($customerNewIncidents <= 0 && ! $massMismatch) {
            return;
        }

        $recipient = trim((string) config(
            'jannayaks.security.integrity.alert_recipient',
            config('jannayaks.integrity.alert_recipient', ''),
        ));
        if ($recipient === '') {
            Log::warning('profile-integrity: founder alert skipped — JANNAYAKS_INTEGRITY_ALERT_RECIPIENT is empty', [
                'run_id' => $summary['run_id'] ?? null,
            ]);

            return;
        }

        $findingsForMail = array_values(array_filter(
            $customerFindings,
            static fn (array $row): bool => $customerNewIncidents > 0 ? ! empty($row['newly_created']) : true,
        ));

        if ($customerNewIncidents <= 0 && $findingsForMail === []) {
            Log::info('profile-integrity: founder alert skipped — findings are demo-page profiles only', [
                'run_id' => $summary['run_id'] ?? null,
                'demo_findings' => count($alertFindings),
                'mass_mismatch_alert' => $massMismatch,
            ]);

            return;
        }

        $mailSummary = $summary;
        $mailSummary['incidents_created'] = $customerNewIncidents;

        try {
            Mail::to($recipient)->send(new ProfileIntegrityAlertMail($mailSummary, $findingsForMail));
            Log::info('profile-integrity: founder alert sent', [
                'run_id' => $summary['run_id'] ?? null,
                'recipient_domain' => Str::after($recipient, '@'),
                'incidents_created' => $customerNewIncidents,
                'mass_mismatch_alert' => $massMismatch,
                'finding_rows' => count($findingsForMail),
                'demo_findings_omitted' => count($alertFindings) - count($customerFindings),
            ]);
        } catch (Throwable $e) {
            Log::error('profile-integrity: founder alert delivery failed — scan results are preserved', [
                'run_id' => $summary['run_id'] ?? null,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }
    }

    /**
     * Living profiles currently listed on /demo-profiles.
     * Same discriminator as DemoProfilesController (published + demo email domain).
     *
     * @return list<int>
     */
    private function livingDemoProfileIdsListedOnDemoPage(): array
    {
        return $this->profileSearch->publishedBaseQuery()
            ->whereHas(
                'user',
                fn ($query) => $query->where('email', 'like', '%'.SitemapService::DEMO_EMAIL_DOMAIN)
            )
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}

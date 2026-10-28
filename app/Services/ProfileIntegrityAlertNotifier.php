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
 */
class ProfileIntegrityAlertNotifier
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array{profile_id: int, slug: string|null, type: string, detection_reason: string, newly_created: bool}>  $alertFindings
     */
    public function notifyIfNeeded(array $summary, array $alertFindings): void
    {
        $newIncidents = (int) ($summary['incidents_created'] ?? 0);
        $massMismatch = (bool) ($summary['mass_mismatch_alert'] ?? false);

        if ($newIncidents <= 0 && ! $massMismatch) {
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
            $alertFindings,
            static fn (array $row): bool => $newIncidents > 0 ? $row['newly_created'] : true,
        ));

        try {
            Mail::to($recipient)->send(new ProfileIntegrityAlertMail($summary, $findingsForMail));
            Log::info('profile-integrity: founder alert sent', [
                'run_id' => $summary['run_id'] ?? null,
                'recipient_domain' => Str::after($recipient, '@'),
                'incidents_created' => $newIncidents,
                'mass_mismatch_alert' => $massMismatch,
                'finding_rows' => count($findingsForMail),
            ]);
        } catch (Throwable $e) {
            Log::error('profile-integrity: founder alert delivery failed — scan results are preserved', [
                'run_id' => $summary['run_id'] ?? null,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }
    }
}

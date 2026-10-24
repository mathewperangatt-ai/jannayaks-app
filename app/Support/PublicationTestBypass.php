<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Profile;
use App\Models\StaffActionLog;

/**
 * Temporary, default-off allowlist for publishing one manual-test profile
 * without a settled payment.
 *
 * This is separate from the editorial preview bypass. It never writes a
 * payment, invoice, receipt, webhook, or settlement, and it never marks an
 * editorial version approved. The exceptional admin publication path cannot
 * use it. Disabling the flag restores the payment gate for later publication.
 */
class PublicationTestBypass
{
    public function enabled(): bool
    {
        return $this->configFlagEnabled(config('jannayaks.publication_test_bypass.enabled', false));
    }

    /**
     * @return list<string>
     */
    public function referenceCodes(): array
    {
        $raw = config('jannayaks.publication_test_bypass.reference_codes', '');
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            return [];
        }

        $codes = [];
        foreach ($raw as $code) {
            if (! is_string($code) && ! is_numeric($code)) {
                continue;
            }
            $code = strtoupper(trim((string) $code));
            if ($code === '' || in_array($code, $codes, true)) {
                continue;
            }
            $codes[] = $code;
        }

        return $codes;
    }

    public function appliesTo(?Application $application): bool
    {
        if (! $this->enabled() || ! $application instanceof Application) {
            return false;
        }

        $profile = $this->uniqueAllowlistedProfile($application);
        if (! $profile instanceof Profile) {
            return false;
        }

        return ! $profile->isPubliclyListed();
    }

    public function wasUsedFor(?Application $application): bool
    {
        if (! $application instanceof Application || $application->id === null) {
            return false;
        }

        return StaffActionLog::query()
            ->where('action', 'application.publication_test_bypass_used')
            ->where('subject_type', $application->getMorphClass())
            ->where('subject_id', $application->id)
            ->exists();
    }

    /**
     * Unpublish stays available after the flag is turned off, but only for a
     * profile this bypass actually published.
     */
    public function mayUnpublish(?Application $application): bool
    {
        if (! $application instanceof Application || ! $this->wasUsedFor($application)) {
            return false;
        }

        $application->loadMissing('profile');

        return $application->profile?->isPubliclyListed() === true;
    }

    public function adminNotice(?Application $application): ?string
    {
        if ($this->appliesTo($application)) {
            $code = strtoupper(trim((string) $application?->profile?->reference_code));

            return 'Publication test active for '.$code.' only. This profile can be published without a settled payment. Payment stays pending and no settlement is recorded. Customer approval must match the exact approved English version. The exceptional admin path cannot be used. The public URL will be visible until an admin unpublishes it. Disable JANNAYAKS_PUBLICATION_TEST_BYPASS_ENABLED to restore the payment gate.';
        }

        if ($this->wasUsedFor($application) && $application?->profile?->isPubliclyListed()) {
            $application->loadMissing('profile');
            $code = strtoupper(trim((string) $application->profile?->reference_code));

            return 'Publication test profile '.($code !== '' ? $code.' ' : '').'is publicly visible without a settled payment. Unpublish it after layout verification. Turning the publication test off does not hide it, and it restores the normal payment gate for any later publication.';
        }

        return null;
    }

    public function publishModalDescription(?Application $application): string
    {
        if ($this->appliesTo($application)) {
            return 'Publication test is active for this allowlisted profile only. Publishing makes the public URL visible without recording a payment. Customer approval of the exact approved English version is required. The exceptional admin path is refused.';
        }

        return 'Admin only. Normal path requires customer approval. Exceptional offline/admin publication is audited.';
    }

    private function uniqueAllowlistedProfile(Application $application): ?Profile
    {
        $application->loadMissing('profile');
        $code = strtoupper(trim((string) $application->profile?->reference_code));
        if ($code === '' || ! in_array($code, $this->referenceCodes(), true)) {
            return null;
        }

        if (Profile::query()->where('reference_code', $code)->count() !== 1) {
            return null;
        }

        return $application->profile;
    }

    private function configFlagEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }
}

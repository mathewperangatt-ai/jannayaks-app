<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Profile;

/**
 * Temporary, default-off allowlist for one synthetic manual-test profile.
 *
 * Payments testing mode suspends payment for every application. This bypass
 * does not. It never writes a payment or settlement, and it never changes
 * an editorial version's status.
 */
class EditorialTestBypass
{
    public function enabled(): bool
    {
        return $this->configFlagEnabled(config('jannayaks.editorial_test_bypass.enabled', false));
    }

    /**
     * @return list<string>
     */
    public function referenceCodes(): array
    {
        $raw = config('jannayaks.editorial_test_bypass.reference_codes', '');
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

        $application->loadMissing('profile');
        $code = strtoupper(trim((string) $application->profile?->reference_code));
        if ($code === '' || ! in_array($code, $this->referenceCodes(), true)) {
            return false;
        }

        // The unique index is the schema guarantee. Refuse unless this code
        // identifies exactly one profile, so a bad allowlist cannot widen.
        if (Profile::query()->where('reference_code', $code)->count() !== 1) {
            return false;
        }

        if ($application->profile?->isPubliclyListed()) {
            return false;
        }

        return true;
    }

    public function adminNotice(?Application $application): ?string
    {
        if (! $this->appliesTo($application)) {
            return null;
        }

        $code = strtoupper(trim((string) $application->profile?->reference_code));

        return 'Test bypass active for '.$code.' only. Payment stays pending and no settlement is recorded. An unapproved English draft may be released to this customer’s private preview without being marked editorially approved. An unapproved draft still cannot be published. Disable JANNAYAKS_EDITORIAL_TEST_BYPASS_ENABLED to restore the normal workflow.';
    }

    public function releaseModalDescription(?Application $application): string
    {
        if ($this->appliesTo($application)) {
            return 'Temporary test bypass is active for this allowlisted profile only. The latest usable English draft can be released to the customer’s private preview. The draft is not marked editorially approved, payment stays pending, and this does not publish the profile.';
        }

        return 'Requires an approved English editorial master. The member can then review, request included revisions, or approve. Does not publish.';
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

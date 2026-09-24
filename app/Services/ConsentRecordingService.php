<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ConsentRecord;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Writes ConsentRecord rows for legally relevant member consents (DPDP).
 *
 * Records are append-only evidence: values are server-generated (never taken
 * from client input beyond the acceptance act itself), idempotent per user +
 * consent key, and backed by a database unique index so concurrent requests
 * cannot create duplicates.
 *
 * Scope is deliberately narrow: interview AI-processing consent and
 * publication-approval consent. Not a general-purpose consent framework.
 */
class ConsentRecordingService
{
    public const NOTICE_VERSION = 'v1';

    public const KEY_INTERVIEW_AI_PROCESSING = 'interview.submission.ai_processing';

    public const KEY_EDITORIAL_APPROVAL_PUBLICATION = 'editorial.approval.publication';

    /**
     * Record the publication-approval consent against the exact application and
     * profile being approved. Called inside the approval transaction so that a
     * consent-write failure rolls the whole approval back.
     */
    public function recordPublicationApproval(
        Application $application,
        User $member,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ConsentRecord {
        return $this->recordOnce(
            $member,
            self::KEY_EDITORIAL_APPROVAL_PUBLICATION,
            $ipAddress,
            $userAgent,
            $application,
            $application->profile_id !== null ? Profile::query()->find($application->profile_id) : null,
        );
    }

    public function recordOnce(
        User $user,
        string $consentKey,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?Application $application = null,
        ?Profile $profile = null,
    ): ConsentRecord {
        $existing = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('consent_key', $consentKey)
            ->first();

        if ($existing instanceof ConsentRecord) {
            return $existing;
        }

        try {
            return ConsentRecord::query()->create([
                'user_id' => $user->id,
                'application_id' => $application?->id,
                'profile_id' => $profile?->id,
                'consent_key' => mb_substr($consentKey, 0, 64),
                'consented' => true,
                'action_at' => now(),
                'notice_version' => self::NOTICE_VERSION,
                'ip_address' => $ipAddress !== null ? mb_substr($ipAddress, 0, 45) : null,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 1000) : null,
            ]);
        } catch (QueryException $e) {
            // A concurrent request won the unique (user_id, consent_key) index;
            // that row is the durable record — return it instead of failing.
            if ($this->isDuplicateConsentViolation($e)) {
                $raced = ConsentRecord::query()
                    ->where('user_id', $user->id)
                    ->where('consent_key', $consentKey)
                    ->first();

                if ($raced instanceof ConsentRecord) {
                    return $raced;
                }
            }

            throw $e;
        }
    }

    private function isDuplicateConsentViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'consent_records_publication_approval_unique')
            || (str_contains($message, 'Unique violation') && str_contains($message, 'consent_records'));
    }
}

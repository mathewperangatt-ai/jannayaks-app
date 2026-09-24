<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\User;

/**
 * Writes ConsentRecord rows for legally relevant member consents (DPDP).
 * The consent_records table is an append log; recordOnce keeps it idempotent
 * per user + consent key so repeated submissions/approvals never duplicate
 * the durable record. Notice copy itself is owned by the business and may be
 * revised by bumping NOTICE_VERSION.
 */
class ConsentRecordingService
{
    public const NOTICE_VERSION = 'v1';

    public const KEY_INTERVIEW_AI_PROCESSING = 'interview.submission.ai_processing';

    public const KEY_EDITORIAL_APPROVAL_PUBLICATION = 'editorial.approval.publication';

    public function recordOnce(
        User $user,
        string $consentKey,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ConsentRecord {
        $existing = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('consent_key', $consentKey)
            ->first();

        if ($existing instanceof ConsentRecord) {
            return $existing;
        }

        return ConsentRecord::query()->create([
            'user_id' => $user->id,
            'consent_key' => mb_substr($consentKey, 0, 64),
            'consented' => true,
            'action_at' => now(),
            'notice_version' => self::NOTICE_VERSION,
            'ip_address' => $ipAddress !== null ? mb_substr($ipAddress, 0, 45) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 1000) : null,
        ]);
    }
}

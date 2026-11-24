<?php

namespace App\Security;

use App\Models\StaffActionLog;
use App\Models\User;
use InvalidArgumentException;

/**
 * Central authorisation boundary for already-published profile
 * administration (Wave 3).
 *
 * Every service path that can mutate published profile content, photographs,
 * correction approvals, integrity incidents or visibility restoration MUST
 * pass through authorize() — there are no alternative routes. Unauthorised
 * attempts are rejected AND recorded as security audit events.
 *
 * Console commands remain available to the owner as operator tooling through
 * Railway shell; they record audit events and refresh trusted snapshots, so
 * the integrity reconciliation still treats their audited actions as
 * authorised. HTTP/Filament paths are hard-gated by this boundary.
 */
class PublishedProfileAuthorizer
{
    public function authorize(User $actor, string $operation): void
    {
        if ($actor->canManagePublishedProfiles()) {
            return;
        }

        // Rejected AND recorded.
        StaffActionLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => 'security.published_profile_access_denied',
            'subject_type' => User::class,
            'subject_id' => $actor->id,
            'before' => null,
            'after' => [
                'operation' => $operation,
                'role' => $actor->role,
                'legacy_override' => (bool) config('jannayaks.security.integrity.published_profile_admin.allow_legacy_admin', true),
            ],
        ]);

        throw new InvalidArgumentException(
            'Only the Jannayaks Security Administrator may perform published-profile operations ('.$operation.'). This attempt has been recorded.',
        );
    }

    /**
     * True when the actor holds exclusive security authority (used for UI
     * visibility and the strict-separation rules on the legacy admin).
     */
    public function isExclusiveSecurityAuthority(User $actor): bool
    {
        return $actor->isSecurityAdmin();
    }
}

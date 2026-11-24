<?php

namespace App\Observers;

use App\Jobs\VerifyPublishedProfileIntegrity;
use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * Wave 3: event-driven integrity triggers.
 *
 * Whenever a published profile's public-state inputs change through the
 * application (HTTP, Filament, console), a queued verification job is
 * dispatched after commit. Legitimate workflows refresh the trusted snapshot
 * inside the same flow, so the queued check classifies healthy — no false
 * alarm. Any application-level write that DOES change public state without
 * refreshing the baseline is verified immediately and, in enforce mode,
 * suspended.
 *
 * Direct database edits do not pass through Eloquent and are covered by the
 * 5-minute content cycle instead. Snapshot/table writes by the integrity
 * service itself are not profile saves and do not loop.
 */
class PublishedProfileIntegrityObserver
{
    /**
     * Profile columns that feed the canonical public state or control
     * visibility. Saves touching none of these cannot alter public content.
     */
    private const PROFILE_PUBLIC_FIELDS = [
        'slug', 'display_name', 'preferred_display_name', 'profession',
        'bio_headline', 'status', 'published_at', 'unpublished_at', 'suspended_at',
    ];

    public function saved(Profile $profile): void
    {
        // Cheap guard: skip saves that wrote nothing or touched none of the
        // public-state / visibility fields (e.g. membership bookkeeping).
        $changed = $profile->getChanges();
        if ($changed === [] || empty(array_intersect_key(array_flip($this->publicFields()), $changed))) {
            return;
        }

        $this->dispatch($profile->id);
    }

    public function deleted(Profile $profile): void
    {
        // Deletion of a published profile is a severe event; the queued
        // verification will find the profile missing and the watchdog reports
        // it. Dispatch against the last known id.
        $this->dispatch($profile->id);
    }

    private function dispatch(int $profileId): void
    {
        VerifyPublishedProfileIntegrity::dispatch($profileId, VerifyPublishedProfileIntegrity::makeRunId());
    }

    /** @return list<string> */
    private function publicFields(): array
    {
        return self::PROFILE_PUBLIC_FIELDS;
    }
}

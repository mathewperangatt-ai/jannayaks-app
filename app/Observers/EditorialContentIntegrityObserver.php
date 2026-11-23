<?php

namespace App\Observers;

use App\Jobs\VerifyPublishedProfileIntegrity;
use App\Models\EditorialContent;
use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * Event-driven integrity trigger for editorial content saves. Drafts for
 * unpublished profiles are skipped cheaply; saves on published profiles
 * dispatch the queued verification (legitimate paths refresh the trusted
 * snapshot in the same flow, so the check classifies healthy).
 */
class EditorialContentIntegrityObserver
{
    public function saved(EditorialContent $content): void
    {
        $profileId = (int) $content->profile_id;
        if ($profileId <= 0 || ! Profile::query()->whereKey($profileId)->where('status', 'published')->exists()) {
            return;
        }

        VerifyPublishedProfileIntegrity::dispatch($profileId, 'evt-'.Str::uuid()->toString());
    }
}

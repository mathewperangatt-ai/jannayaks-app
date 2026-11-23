<?php

namespace App\Observers;

use App\Jobs\VerifyPublishedProfileIntegrity;
use App\Models\MediaItem;
use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * Event-driven integrity trigger for media saves/deletes. Polymorphic rows
 * that are not profile photographs are skipped cheaply.
 */
class MediaItemIntegrityObserver
{
    public function saved(MediaItem $media): void
    {
        $this->dispatchForProfileMedia($media);
    }

    public function deleted(MediaItem $media): void
    {
        $this->dispatchForProfileMedia($media);
    }

    private function dispatchForProfileMedia(MediaItem $media): void
    {
        if ((string) $media->mediable_type !== Profile::class) {
            return;
        }

        $profileId = (int) $media->mediable_id;
        if ($profileId <= 0 || ! Profile::query()->whereKey($profileId)->where('status', 'published')->exists()) {
            return;
        }

        VerifyPublishedProfileIntegrity::dispatch($profileId, VerifyPublishedProfileIntegrity::makeRunId());
    }
}

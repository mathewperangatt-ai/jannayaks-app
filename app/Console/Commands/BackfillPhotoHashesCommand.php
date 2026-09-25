<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillPhotoHashesCommand extends Command
{
    protected $signature = 'jannayaks:backfill-photo-hashes';

    protected $description = 'Compute photo_sha256 for existing profile photographs whose hash is missing. Idempotent; never alters photo bytes.';

    public function handle(): int
    {
        $hashed = 0;
        $missing = 0;
        $skipped = 0;

        $pending = MediaItem::query()
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->whereNull('photo_sha256')
            ->orderBy('id')
            ->get();

        foreach ($pending as $media) {
            $diskName = filled($media->disk)
                ? (string) $media->disk
                : (string) config('jannayaks.media.public_disk', 'public');
            $disk = Storage::disk($diskName);
            $key = (string) $media->storage_path_key;

            // A missing historical object must never receive an invented hash.
            if ($key === '' || ! $disk->exists($key)) {
                $missing++;
                $this->warn("MISSING: photo #{$media->id} ({$diskName}/{$key}) — object not found; hash left null.");

                continue;
            }

            $hash = hash('sha256', (string) $disk->get($key));

            // Guarded update keeps the command idempotent under concurrency.
            $updated = MediaItem::query()
                ->whereKey($media->id)
                ->whereNull('photo_sha256')
                ->update(['photo_sha256' => $hash]);

            if ($updated === 1) {
                $hashed++;
                $this->info("HASHED: photo #{$media->id}");
            } else {
                $skipped++;
                $this->line("SKIPPED: photo #{$media->id} — hash was set concurrently.");
            }
        }

        $this->info("Done. hashed={$hashed} missing={$missing} skipped={$skipped}.");

        return self::SUCCESS;
    }
}

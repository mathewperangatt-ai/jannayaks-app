<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Services\InMemoriamLifecycleService;
use App\Services\InMemoriamUrlService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicInMemoriamMediaController extends Controller
{
    public function __construct(
        private InMemoriamUrlService $urls,
        private InMemoriamLifecycleService $lifecycle,
    ) {}

    public function show(string $slug, MediaItem $media): StreamedResponse
    {
        $profile = $this->urls->findByPublicSlug($slug);
        if ($profile === null || ! $this->lifecycle->isPubliclyVisible($profile)) {
            abort(404);
        }

        if ((string) $media->mediable_type !== $profile->getMorphClass()
            || (int) $media->mediable_id !== (int) $profile->id) {
            abort(404);
        }

        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO
            || $media->privacy !== MediaItem::PRIVACY_PUBLIC
            || $media->review_status !== MediaItem::REVIEW_APPROVED
            || ! filled($media->storage_path_key)) {
            abort(404);
        }

        $diskName = filled($media->disk) ? (string) $media->disk : 'public';
        $disk = Storage::disk($diskName);
        if (! $disk->exists($media->storage_path_key)) {
            abort(404);
        }

        $mime = filled($media->mime_type) ? (string) $media->mime_type : 'image/jpeg';

        return $disk->response($media->storage_path_key, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

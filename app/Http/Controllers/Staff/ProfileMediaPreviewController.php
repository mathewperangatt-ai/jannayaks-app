<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileMediaPreviewController extends Controller
{
    /**
     * Editorial staff preview of a profile photograph (including pending/rejected).
     * Does not use the public publication gate.
     */
    public function __invoke(MediaItem $media): StreamedResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->canManageEditorial()) {
            abort(403);
        }

        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO || ! filled($media->storage_path_key)) {
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
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

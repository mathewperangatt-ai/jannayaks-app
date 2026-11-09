<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Services\CustomerEditorialWorkflowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerProfilePreviewPhotoController extends Controller
{
    /**
     * Stream one of the customer's own approved profile photographs inside the
     * customer preview, before publication. Only the authenticated owner of a
     * released preview receives it; everyone else gets 404, so the existence of
     * another customer's record is never confirmed. The photograph must belong
     * to the application's own profile and pass the same approval rule as
     * public display, so pending, rejected and enhancement candidates are never
     * served. Public photo delivery is unchanged.
     */
    public function show(Application $application, MediaItem $media): StreamedResponse
    {
        $member = Auth::user();
        if ($member === null || (int) $application->user_id !== (int) $member->id) {
            abort(404);
        }

        if (! app(CustomerEditorialWorkflowService::class)->canMemberViewPreview($application)) {
            abort(404);
        }

        $profile = $application->profile;
        if (! $profile instanceof Profile
            || (string) $media->mediable_type !== $profile->getMorphClass()
            || (int) $media->mediable_id !== (int) $profile->id
            || ! $media->isApprovedForPublicDisplay()) {
            abort(404);
        }

        $disk = Storage::disk(filled($media->disk) ? (string) $media->disk : 'public');
        if (! $disk->exists($media->storage_path_key)) {
            abort(404);
        }

        return $disk->response($media->storage_path_key, null, [
            'Content-Type' => filled($media->mime_type) ? (string) $media->mime_type : 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

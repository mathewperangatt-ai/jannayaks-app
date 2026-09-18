<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\MediaItem;
use App\Services\ApplicationPaymentStateService;
use App\Services\ProfileMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileMediaController extends Controller
{
    public function __construct(private ProfileMediaService $media) {}

    public function show(Application $application): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
            return redirect()->route('applications.payment', $application);
        }

        $profile = $application->profile;
        if (! $profile) {
            return redirect()
                ->route('applications.show', $application)
                ->with('error', 'Your profile is not ready for photographs yet.');
        }

        $tier = $this->media->authoritativeTier($profile) ?? (string) $application->package_tier;
        $limit = $this->media->photoSlotLimitForTier($tier);
        $photos = $profile->media()
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->orderByDesc('is_primary')
            ->orderBy('display_order')
            ->get();

        return view('application.media', [
            'application' => $application,
            'profile' => $profile,
            'photos' => $photos,
            'tier' => $tier,
            'limit' => $limit,
            'count' => $photos->count(),
            'maxKb' => (int) config('jannayaks.media.max_upload_kb', 5120),
            'allowsVideo' => (bool) config('jannayaks.tier_pricing.packages.'.$tier.'.includes_video_link', false),
            'videoLinks' => $profile->externalLinks()->where('link_type', 'video')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, Application $application): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
            return redirect()->route('applications.payment', $application);
        }

        $profile = $application->profile;
        if (! $profile) {
            return redirect()
                ->route('applications.show', $application)
                ->with('error', 'Your profile is not ready for photographs yet.');
        }

        $maxKb = (int) config('jannayaks.media.max_upload_kb', 5120);
        $request->validate([
            'photo' => ['required', 'file', 'max:'.$maxKb],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'make_primary' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->media->uploadProfilePhoto(
                profile: $profile,
                file: $request->file('photo'),
                actor: $user,
                altText: $request->input('alt_text'),
                makePrimary: $request->boolean('make_primary'),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('applications.media', $application)
            ->with('status', 'Photograph uploaded.');
    }

    public function setPrimary(Application $application, MediaItem $media): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        $profile = $application->profile;
        if (! $profile) {
            abort(404);
        }

        $this->media->setPrimary($profile, $media, $user);

        return redirect()
            ->route('applications.media', $application)
            ->with('status', 'Primary photograph updated.');
    }

    public function destroy(Application $application, MediaItem $media): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        $profile = $application->profile;
        if (! $profile) {
            abort(404);
        }

        $this->media->deletePhoto($profile, $media, $user);

        return redirect()
            ->route('applications.media', $application)
            ->with('status', 'Photograph removed.');
    }
}

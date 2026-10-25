<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\MediaItem;
use App\Services\ApplicationPaymentStateService;
use App\Services\ProfileMediaService;
use App\Support\ProfileEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $limit = $this->media->photoSlotLimitForApplication($application);
        $photos = $profile->media()
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->orderByDesc('is_primary')
            ->orderBy('display_order')
            ->get();
        $occupying = $photos->filter(fn (MediaItem $photo): bool => in_array($photo->review_status, [
            MediaItem::REVIEW_PENDING,
            MediaItem::REVIEW_APPROVED,
        ], true))->values();
        $rejected = $photos->filter(fn (MediaItem $photo): bool => $photo->review_status === MediaItem::REVIEW_REJECTED)->values();
        $slots = $occupying->all();
        foreach ($rejected as $photo) {
            if (count($slots) >= $limit) {
                break;
            }
            $slots[] = $photo;
        }
        while (count($slots) < $limit) {
            $slots[] = null;
        }
        $shownRejected = collect($slots)->filter()->pluck('id');
        $count = $occupying->count();

        return view('application.media', [
            'application' => $application,
            'profile' => $profile,
            'photos' => $photos,
            'slots' => $slots,
            'extraRejected' => $rejected->reject(fn (MediaItem $photo): bool => $shownRejected->contains($photo->id))->values(),
            'tier' => $tier,
            'limit' => $limit,
            'count' => $count,
            'remaining' => max(0, $limit - $count),
            'maxKb' => (int) config('jannayaks.media.max_upload_kb', 5120),
            'allowsVideo' => ProfileEntitlements::allowsVideoLink($application),
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
            'photo' => ['sometimes', 'file', 'max:'.$maxKb],
            'photos' => ['sometimes', 'array'],
            'photos.*' => ['file', 'max:'.$maxKb],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'make_primary' => ['sometimes', 'boolean'],
        ]);

        $incoming = $request->file('photos');
        $files = is_array($incoming) ? array_values($incoming) : [];
        if ($files === [] && $request->file('photo') instanceof UploadedFile) {
            $files = [$request->file('photo')];
        }
        $files = array_values(array_filter($files, fn ($file): bool => $file instanceof UploadedFile));
        if ($files === []) {
            throw ValidationException::withMessages([
                'photo' => 'Choose a photograph to upload.',
            ]);
        }

        $remaining = max(0, $this->media->photoSlotLimitForApplication($application) - $this->media->profilePhotoCount($profile));
        if ($remaining < 1) {
            throw ValidationException::withMessages([
                'photo' => 'A profile can include at most '.$this->media->photoSlotLimitForApplication($application).' photographs.',
            ]);
        }

        $accepted = array_slice($files, 0, $remaining);
        $overflow = count($files) - count($accepted);
        $saved = 0;
        foreach ($accepted as $index => $file) {
            $this->media->uploadProfilePhoto(
                profile: $profile,
                file: $file,
                actor: $user,
                altText: $index === 0 ? $request->input('alt_text') : null,
                makePrimary: $index === 0 && $request->boolean('make_primary'),
            );
            $saved++;
        }

        $redirect = redirect()->route('applications.media', $application);
        if ($overflow > 0) {
            return $redirect
                ->with('status', $saved.' photograph'.($saved === 1 ? '' : 's').' uploaded.')
                ->withErrors(['photo' => $overflow.' additional photograph'.($overflow === 1 ? '' : 's').' exceeded the remaining slots and '.($overflow === 1 ? 'was' : 'were').' not saved.']);
        }

        return $redirect->with('status', $saved === 1 ? 'Photograph uploaded.' : $saved.' photographs uploaded.');
    }

    public function preview(Application $application, MediaItem $media): StreamedResponse
    {
        $user = Auth::user();
        if (! $user || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        $profile = $application->profile;
        if (! $profile
            || $media->media_type !== MediaItem::TYPE_PROFILE_PHOTO
            || (int) $media->mediable_id !== (int) $profile->id
            || $media->mediable_type !== $profile->getMorphClass()
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
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

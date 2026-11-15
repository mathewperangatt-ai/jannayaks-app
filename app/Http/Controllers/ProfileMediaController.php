<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Services\ApplicationPaymentStateService;
use App\Services\ProfileMediaService;
use App\Services\ProfileUrlService;
use App\Support\ProfileEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

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

        $profile = $this->draftProfileFor($application);
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

        return view('application.media', [
            'application' => $application,
            'profile' => $profile,
            'photos' => $photos,
            'tier' => $tier,
            'limit' => $limit,
            'count' => $photos->count(),
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

        $profile = $this->draftProfileFor($application);
        if (! $profile) {
            return redirect()
                ->route('applications.show', $application)
                ->with('error', 'Your profile is not ready for photographs yet.');
        }

        $maxKb = (int) config('jannayaks.media.max_upload_kb', 5120);
        $remaining = max(0, $this->media->photoSlotLimitForApplication($application) - $this->media->profilePhotoCount($profile));

        // photo[] (multi-select) and photo (legacy single file) both work.
        $files = $request->file('photo');
        $isBatch = is_array($files);
        $files = array_values(array_filter($isBatch ? $files : [$files]));

        if ($isBatch) {
            $request->validate([
                'photo' => ['required', 'array'],
                'photo.*' => ['file', 'max:'.$maxKb],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'make_primary' => ['sometimes', 'boolean'],
            ]);

            // Pre-batch checks run before ANY photograph is stored: an
            // oversized selection or a forbidden file type stores nothing.
            if (count($files) > $remaining) {
                throw ValidationException::withMessages([
                    'photo' => $remaining === 0
                        ? $this->media->photoLimitMessage($this->media->photoSlotLimitForApplication($application))
                        : 'You may upload '.$remaining.' more photograph'.($remaining === 1 ? '' : 's').' in this batch.',
                ]);
            }

            $allowed = (array) config('jannayaks.media.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp']);
            foreach ($files as $file) {
                $extension = strtolower((string) $file->getClientOriginalExtension());
                if (! in_array($extension, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'photo' => ($file->getClientOriginalName() ?: 'A selected file')
                            .' is not a JPEG, PNG, or WebP photograph. Nothing from this batch was uploaded.',
                    ]);
                }
            }
        } else {
            $request->validate([
                'photo' => ['required', 'file', 'max:'.$maxKb],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'make_primary' => ['sometimes', 'boolean'],
            ]);
        }

        $makePrimary = $request->boolean('make_primary');
        $uploadedNames = [];

        // Each photograph uploads through the individually transactional
        // service path (validation, optimisation, SHA-256, storage, audit).
        // The whole batch is pre-validated, so a mid-batch failure means a
        // real storage or service-level rejection: earlier successes stay
        // stored and the customer is told exactly which file failed and why.
        foreach ($files as $index => $file) {
            $name = $file->getClientOriginalName() ?: 'photograph #'.($index + 1);

            try {
                $this->media->uploadProfilePhoto(
                    profile: $profile,
                    file: $file,
                    actor: $user,
                    altText: $request->input('alt_text'),
                    makePrimary: $index === 0 && $makePrimary,
                );
            } catch (ValidationException $e) {
                if ($uploadedNames === []) {
                    throw $e;
                }

                $reason = $e->errors()['photo'][0] ?? ($e->errors()['alt_text'][0] ?? 'The photograph was rejected.');
                return redirect()
                    ->route('applications.media', $application)
                    ->with('status', count($uploadedNames).' photograph'.(count($uploadedNames) === 1 ? '' : 's').' uploaded: '.implode(', ', $uploadedNames).'.')
                    ->with('error', $name.' was not uploaded: '.$reason.' You can upload the remaining photographs now.');
            }

            $uploadedNames[] = $name;
        }

        if (count($uploadedNames) === 1) {
            return redirect()
                ->route('applications.media', $application)
                ->with('status', 'Photograph uploaded: '.$uploadedNames[0].'.');
        }

        return redirect()
            ->route('applications.media', $application)
            ->with('status', count($uploadedNames).' photographs uploaded: '.implode(', ', $uploadedNames).'.');
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

    /** Same draft profile the Profile URL page creates; no URL is assigned here. */
    private function draftProfileFor(Application $application): ?Profile
    {
        if ($application->profile instanceof Profile) {
            return $application->profile;
        }

        try {
            $profile = app(ProfileUrlService::class)->ensureDraftProfileForApplication($application);
        } catch (InvalidArgumentException) {
            return null;
        }

        $application->setRelation('profile', $profile);

        return $profile;
    }
}

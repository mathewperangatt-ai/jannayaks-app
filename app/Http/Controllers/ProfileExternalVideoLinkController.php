<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationPaymentStateService;
use App\Services\ExternalVideoLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfileExternalVideoLinkController extends Controller
{
    public function __construct(private ExternalVideoLinkService $videos) {}

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
                ->with('error', 'Your profile is not ready for video links yet.');
        }

        $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $link = $this->videos->submit(
                profile: $profile,
                actor: $user,
                url: (string) $request->input('url'),
                label: $request->input('label'),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        $message = $link->replaces_link_id
            ? 'Video link change requested. The current public link stays active until Jannayaks approves the change.'
            : 'Video link submitted for review.';

        return redirect()
            ->route('applications.media', $application)
            ->with('status', $message);
    }
}

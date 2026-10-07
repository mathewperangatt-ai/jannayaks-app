<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use App\Models\User;
use App\Services\PostPublicationUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Customer-facing post-publication profile maintenance (Pass 1).
 *
 * A published-profile owner may submit ONE bundled update request; the
 * request is classified complimentary/paid against the publication-anchored
 * 3-month cycle. No editorial content is modified here — the request only
 * enters the maintenance queue.
 */
class ProfileMaintenanceController extends Controller
{
    public function show(Request $request, Application $application): View|RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $profile = $this->publishableProfile($application);

        $eligibility = app(PostPublicationUpdateService::class)->eligibilityFor($profile);

        return view('application.maintenance-request', [
            'application' => $application,
            'member' => $member,
            'profile' => $profile,
            'eligibility' => $eligibility,
            'openRequest' => $this->openRequest($application),
        ]);
    }

    public function store(Request $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $this->publishableProfile($application);

        $validated = $request->validate([
            'request_text' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        try {
            $maintenance = app(PostPublicationUpdateService::class)->submit(
                $application,
                $member,
                (string) $validated['request_text'],
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('applications.maintenance.show', $application)
                ->withErrors(['request_text' => $e->getMessage()]);
        }

        $classification = $maintenance->billing_classification === EditorialRevisionRequest::BILLING_COMPLIMENTARY
            ? 'Complimentary update'
            : 'Paid update request';

        return redirect()
            ->route('applications.show', $application)
            ->with('maintenance_status', 'Update request submitted — '.$classification.'. The editorial team will review it; no changes go live without your approval.');
    }

    public function approve(Request $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $this->publishableProfile($application);

        $validated = $request->validate([
            'english_editorial_content_id' => ['required', 'integer'],
            'confirm_approval' => ['accepted'],
        ]);

        try {
            app(PostPublicationUpdateService::class)->approveMaintenancePreview(
                $application,
                $member,
                (int) $validated['english_editorial_content_id'],
                $request->ip(),
                (string) $request->userAgent(),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('applications.preview', $application)
                ->withErrors(['maintenance_approval' => $e->getMessage()]);
        }

        return redirect()
            ->route('applications.preview', $application)
            ->with('status', 'Thank you — your approval has been recorded. Jannayaks will complete the publication of your updated profile.');
    }

    public function correction(Request $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $this->publishableProfile($application);

        $validated = $request->validate([
            'correction_text' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        try {
            app(PostPublicationUpdateService::class)->requestCorrection(
                $application,
                $member,
                (string) $validated['correction_text'],
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('applications.preview', $application)
                ->withErrors(['correction_text' => $e->getMessage()]);
        }

        return redirect()
            ->route('applications.preview', $application)
            ->with('status', 'Your correction request has been passed to the editorial team. The corrected profile will return here for your approval.');
    }

    private function authenticatedOwner(Application $application): User
    {
        $user = Auth::user();
        if (! $user instanceof User || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        return $user;
    }

    private function publishableProfile(Application $application): \App\Models\Profile
    {
        $profile = $application->profile;

        if ($application->status !== Application::STATUS_PUBLISHED
            || $profile === null
            || ! $profile->isPubliclyListed()
            || $profile->published_at === null) {
            abort(404);
        }

        return $profile;
    }

    private function openRequest(Application $application): ?EditorialRevisionRequest
    {
        return EditorialRevisionRequest::query()
            ->where('application_id', $application->id)
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->whereIn('status', [EditorialRevisionRequest::STATUS_SUBMITTED, EditorialRevisionRequest::STATUS_IN_PROGRESS])
            ->orderByDesc('id')
            ->first();
    }
}

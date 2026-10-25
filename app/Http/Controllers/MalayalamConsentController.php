<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\User;
use App\Services\MalayalamConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class MalayalamConsentController extends Controller
{
    public function __construct(private MalayalamConsentService $consents) {}

    public function show(Request $request, Application $application): View|RedirectResponse
    {
        $member = $this->owner($application);
        $malayalam = $this->consents->consentCandidate($application);
        if ($malayalam === null) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['malayalam' => 'There is no approved Malayalam text waiting for your consent.']);
        }

        return view('application.malayalam-consent', [
            'application' => $application,
            'member' => $member,
            'malayalam' => $malayalam,
            'alreadyConsented' => $this->consents->hasCurrentCustomerConsent($application),
            'alreadyPublished' => (int) $application->published_malayalam_editorial_content_id === (int) $malayalam->id,
        ]);
    }

    public function store(Request $request, Application $application): RedirectResponse
    {
        $member = $this->owner($application);
        $malayalamId = (int) $request->input('malayalam_editorial_content_id');

        try {
            $this->consents->recordCustomerConsent($application, $member, $malayalamId);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['malayalam' => $e->getMessage()]);
        }

        return redirect()
            ->route('applications.malayalam-consent.show', $application)
            ->with('status', 'Your consent for this Malayalam text has been recorded.');
    }

    private function owner(Application $application): User
    {
        $user = Auth::user();
        if (! $user instanceof User || (int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        return $user;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerProfileApprovalRequest;
use App\Http\Requests\CustomerRevisionRequest;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\User;
use App\Services\CustomerEditorialWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerProfilePreviewController extends Controller
{
    public function show(Request $request, Application $application): View|RedirectResponse
    {
        $member = $this->authenticatedOwner($application);

        $workflow = app(CustomerEditorialWorkflowService::class);
        if (! $workflow->canMemberViewPreview($application)) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['preview' => 'Your profile preview is not available yet.']);
        }

        $english = EditorialContent::query()->find($application->preview_english_editorial_content_id);
        $malayalam = $application->preview_malayalam_editorial_content_id
            ? EditorialContent::query()->find($application->preview_malayalam_editorial_content_id)
            : null;

        if (! $english) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['preview' => 'Preview content is unavailable. Please contact support.']);
        }

        return view('application.profile-preview', [
            'application' => $application,
            'member' => $member,
            'english' => $english,
            'malayalam' => $malayalam,
            'statusLabel' => $workflow->memberFacingStatusLabel($application),
            'remainingRevisions' => $workflow->remainingIncludedRevisionRounds($application),
            'canRequestRevision' => $application->status === Application::STATUS_EDITORIAL_APPROVED
                && $application->customer_approved_at === null
                && $workflow->remainingIncludedRevisionRounds($application) > 0,
            'canRequestFactualCorrection' => $application->status === Application::STATUS_EDITORIAL_APPROVED
                && $application->customer_approved_at === null,
            'canApprove' => $application->status === Application::STATUS_EDITORIAL_APPROVED
                && $application->customer_approved_at === null,
            'alreadyApproved' => $application->customer_approved_at !== null,
        ]);
    }

    public function requestRevision(CustomerRevisionRequest $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);

        try {
            app(CustomerEditorialWorkflowService::class)->requestRevision(
                $application,
                $member,
                (string) $request->validated('request_text'),
                (string) $request->validated('request_type'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['revision' => $e->getMessage()])->withInput();
        }

        $type = (string) $request->validated('request_type');
        $message = $type === EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION
            ? 'Your factual/typographical correction request has been submitted to the editorial team.'
            : 'Your revision request has been submitted. This uses one included revision round.';

        return redirect()
            ->route('applications.preview', $application)
            ->with('status', $message);
    }

    public function approve(CustomerProfileApprovalRequest $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);

        try {
            // The publication-approval consent is recorded atomically inside
            // approvePreview's transaction — approval and consent commit together.
            app(CustomerEditorialWorkflowService::class)->approvePreview(
                $application,
                $member,
                (int) $request->validated('english_editorial_content_id'),
                $request->ip(),
                (string) $request->userAgent(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return redirect()
            ->route('applications.preview', $application)
            ->with('status', 'Thank you. You have approved this profile for publication.');
    }

    private function authenticatedOwner(Application $application): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        if ((int) $application->user_id !== (int) $user->id) {
            abort(403, 'This application is not yours.');
        }

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Staff;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\PublicProfilePresentationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff-side preview of the publication-ready presentation, BEFORE the
 * customer is asked to approve it. Renders the same shared presentation
 * and wrapper blade as the customer preview; photographs stream through
 * the existing staff media preview route; nothing is publishable here.
 */
class StaffProfilePresentationPreviewController extends \App\Http\Controllers\Controller
{
    public function __invoke(Request $request, Application $application): View
    {
        $user = $request->user();
        if (! $user || ! $user->canManageEditorial()) {
            abort(403);
        }

        $workflow = app(CustomerEditorialWorkflowService::class);
        $english = $application->preview_english_editorial_content_id !== null
            ? EditorialContent::query()->find($application->preview_english_editorial_content_id)
            : null;
        $malayalam = $workflow->malayalamShownInPreview($application);

        if (! $english) {
            abort(404);
        }

        $presentation = app(PublicProfilePresentationService::class)
            ->presentForCustomerPreview(
                $application->profile,
                $english,
                $malayalam,
                (string) $request->query('lang', 'en'),
            );

        return view('application.profile-preview', $presentation + [
            'application' => $application,
            'member' => $user,
            'statusLabel' => 'Staff preview — not sent to the customer',
            'remainingRevisions' => $workflow->remainingIncludedRevisionRounds($application),
            'canRequestRevision' => false,
            'canRequestFactualCorrection' => false,
            'canApprove' => false,
            'alreadyApproved' => $application->customer_approved_at !== null,
            'maintenanceRequest' => null,
            'canApproveMaintenance' => false,
            'maintenanceCorrectionPending' => false,
            'maintenanceApproved' => false,
            'photoUrlOf' => fn ($item) => route('staff.profile-media.preview', ['media' => $item]),
            'langUrlOf' => fn (string $lang) => route('staff.applications.presentation-preview', ['application' => $application, 'lang' => $lang]),
            'showEngagement' => false,
            'showPermanentUrl' => false,
            'galleryLinksClickable' => false,
            'showBackLink' => false,
            'listingUrl' => url('/admin/applications/'.$application->id),
            'listingLabel' => 'Application',
            'isDemonstration' => false,
            'viewerReactions' => [],
        ]);
    }
}

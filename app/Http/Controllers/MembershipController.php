<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\MembershipLifecycleService;
use App\Services\ProfileUrlService;
use App\Services\RazorpayPaymentService;
use App\Support\PricingAmounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class MembershipController extends Controller
{
    public function __construct(
        private readonly MembershipLifecycleService $lifecycle,
        private readonly RazorpayPaymentService $razorpay,
        private readonly ProfileUrlService $profileUrls,
    ) {}

    public function show(Request $request, Profile $profile): View
    {
        $user = $request->user();
        abort_unless($user !== null && (int) $profile->user_id === (int) $user->id, 403);

        $membership = $profile->membership;
        abort_if($membership === null, 404);

        $phase = $this->lifecycle->lifecyclePhase($membership);
        $graceEnds = $this->lifecycle->graceEndsOn($membership);

        return view('membership.show', [
            'profile' => $profile,
            'membership' => $membership,
            'phase' => $phase,
            'graceEndsOn' => $graceEnds,
            'isPublic' => $this->profileUrls->isPubliclyVisible($profile),
            'canRenew' => $phase !== 'retention_ended',
            'amounts' => PricingAmounts::forAnnualMembership(),
        ]);
    }

    public function renew(Request $request, Profile $profile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && (int) $profile->user_id === (int) $user->id, 403);

        $membership = $profile->membership;
        abort_if($membership === null, 404);

        try {
            $payment = $this->razorpay->createMembershipRenewalPaymentLink($membership, $user);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('membership.show', $profile)
                ->withErrors(['renewal' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('membership.show', $profile)
                ->withErrors(['renewal' => 'Unable to start renewal payment. Please try again later.']);
        }

        if (is_string($payment->razorpay_link_url) && $payment->razorpay_link_url !== '') {
            return redirect()->away($payment->razorpay_link_url);
        }

        // When Razorpay is disabled (local/test), show the membership page with pending attempt info.
        return redirect()
            ->route('membership.show', $profile)
            ->with('status', 'Renewal payment initiated (gateway disabled). Settlement still requires an authoritative webhook.');
    }
}

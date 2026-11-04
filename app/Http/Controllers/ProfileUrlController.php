<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectPersonalProfileUrlRequest;
use App\Http\Requests\UpdateHostingDestinationRequest;
use App\Models\Application;
use App\Models\User;
use App\Services\ProfileQrCodeService;
use App\Services\ProfileUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class ProfileUrlController extends Controller
{
    public function __construct(
        private ProfileUrlService $profileUrls,
        private ProfileQrCodeService $qrCodes,
    ) {}

    public function show(Request $request, Application $application): View|RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $tier = strtolower((string) $application->package_tier);
        $canSelectPersonal = $this->profileUrls->packageTierAllowsPersonalSlug($tier);

        $profile = $application->profile;
        if (! $profile && $canSelectPersonal) {
            $profile = $this->profileUrls->ensureDraftProfileForApplication($application);
            $application->refresh();
        }

        if (! $profile) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['profile_url' => 'Your profile is not ready yet.']);
        }

        $isPublished = $this->profileUrls->isPubliclyVisible($profile);
        $canonicalUrl = $this->profileUrls->canonicalPublicUrl($profile);
        $history = $profile->slugRedirects()->orderByDesc('id')->limit(20)->get();
        $personalSlugLocked = $this->profileUrls->hasPermanentPersonalSlug($profile);
        $verifiedName = (string) ($profile->full_name ?: $application->full_name);
        $suggestions = $canSelectPersonal
            ? $this->profileUrls->suggestPersonalSlugs($verifiedName, (int) $profile->id)
            : [];

        $hostingDestination = (string) (
            $application->hosting_destination
            ?: Application::HOSTING_DESTINATION_JANNAYAKS
        );

        // Hosting-option price lines, shown before reservation. The Jannayaks
        // line comes from the authoritative Standard Profile pricing; the
        // IconsHerald line mirrors the IconsHerald production configuration.
        $standard = \App\Support\PricingAmounts::forStandardProfile();
        $hostingPrices = [
            'jannayaks' => [
                'price_line' => '₹'.number_format($standard['base_paise'] / 100).' + GST'
                    .' (total ₹'.number_format($standard['amount_incl_paise'] / 100).' including GST)',
            ],
            'iconsherald' => [
                'price_line' => (string) config('jannayaks.hosting_destinations.iconsherald.price_line'),
            ],
        ];

        return view('application.profile-url', [
            'application' => $application,
            'member' => $member,
            'profile' => $profile,
            'tier' => $tier,
            'canSelectPersonal' => $canSelectPersonal,
            'isPublished' => $isPublished,
            'canonicalUrl' => $canonicalUrl,
            'history' => $history,
            'preferredSlug' => $application->preferred_slug,
            'personalSlugLocked' => $personalSlugLocked,
            'verifiedName' => $verifiedName,
            'suggestions' => $suggestions,
            'hostingDestination' => $hostingDestination,
            'hostingDestinationLocked' => $isPublished,
            'hostingPrices' => $hostingPrices,
        ]);
    }

    public function update(SelectPersonalProfileUrlRequest $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $tier = strtolower((string) $application->package_tier);
        $profile = $application->profile;

        if (! $profile && $this->profileUrls->packageTierAllowsPersonalSlug($tier)) {
            $profile = $this->profileUrls->ensureDraftProfileForApplication($application);
            $application->refresh();
        }

        if (! $profile) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['profile_url' => 'Your profile is not ready yet.']);
        }

        if ((int) $profile->user_id !== (int) $member->id) {
            abort(403);
        }

        try {
            $this->profileUrls->selectPersonalSlug(
                $profile,
                $member,
                (string) $request->validated('slug'),
                (string) $application->package_tier,
            );
            $this->profileUrls->setHostingDestination(
                $application->fresh(),
                (string) $request->validated('hosting_destination'),
            );
        } catch (InvalidArgumentException $e) {
            $field = str_contains($e->getMessage(), 'Hosting destination')
                ? 'hosting_destination'
                : 'slug';

            return back()->withErrors([$field => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('applications.profile-url', $application)
            ->with('status', 'Your personal profile URL has been reserved. It becomes public after approval.');
    }

    public function updateHosting(UpdateHostingDestinationRequest $request, Application $application): RedirectResponse
    {
        $member = $this->authenticatedOwner($application);
        $profile = $application->profile;

        if (! $profile) {
            $tier = strtolower((string) $application->package_tier);
            if ($this->profileUrls->packageTierAllowsPersonalSlug($tier)) {
                $profile = $this->profileUrls->ensureDraftProfileForApplication($application);
                $application->refresh();
            }
        }

        if (! $profile) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['hosting_destination' => 'Your profile is not ready yet.']);
        }

        if ((int) $profile->user_id !== (int) $member->id) {
            abort(403);
        }

        try {
            $this->profileUrls->setHostingDestination(
                $application->fresh(),
                (string) $request->validated('hosting_destination'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['hosting_destination' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('applications.profile-url', $application)
            ->with('status', 'Your hosting destination has been saved.');
    }

    public function qr(Request $request, Application $application): Response|RedirectResponse
    {
        $this->authenticatedOwner($application);
        $profile = $application->profile;

        if (! $profile) {
            abort(404);
        }

        try {
            $png = $this->qrCodes->pngBytes($profile);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('applications.profile-url', $application)
                ->withErrors(['qr' => $e->getMessage()]);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline; filename="jannayaks-profile-qr.png"',
        ]);
    }

    public function availability(Request $request, Application $application): JsonResponse
    {
        $this->authenticatedOwner($application);

        $raw = (string) $request->query('slug', '');
        $profile = $application->profile;
        $fullName = (string) ($profile?->full_name ?: $application->full_name);
        $result = $this->profileUrls->validatePersonalSlugCandidate(
            $raw,
            $profile?->id,
            $fullName,
            $profile?->profession,
        );

        return response()->json([
            'available' => $result['ok'] === true,
            'slug' => $result['slug'] ?? null,
            'message' => $result['ok'] === true
                ? 'This personal URL is available.'
                : ($result['error'] ?? 'Not available.'),
        ]);
    }

    private function authenticatedOwner(Application $application): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ((int) $application->user_id !== (int) $user->id) {
            abort(403);
        }

        return $user;
    }
}

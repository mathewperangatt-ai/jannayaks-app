<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicProfileUrlController extends Controller
{
    public function __construct(private ProfileUrlService $profileUrls) {}

    /**
     * Resolve /p/{slug}: redirect historical URLs; expose only published profiles.
     * Full public profile presentation belongs to a later phase — this is URL infrastructure only.
     */
    public function show(string $slug): View|RedirectResponse|Response
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '' || str_contains($slug, '/') || str_contains($slug, '\\') || str_contains($slug, '..')) {
            abort(404);
        }

        $profile = Profile::query()
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->first();

        if ($profile) {
            return $this->renderOrHide($profile);
        }

        $redirect = $this->profileUrls->findActiveRedirect($normalized);
        if ($redirect === null) {
            abort(404);
        }

        $target = $redirect->redirectable;
        if (! $target instanceof Profile) {
            abort(404);
        }

        // Historical URLs must never become an alias for another profile.
        if ((int) $redirect->redirectable_id !== (int) $target->id) {
            abort(404);
        }

        if (! filled($target->slug)) {
            abort(404);
        }

        // Unpublished profiles: do not leak via redirect destination either.
        if (! $this->profileUrls->isPubliclyVisible($target)) {
            abort(404);
        }

        return redirect()->route('profiles.public', ['slug' => $target->slug], 301);
    }

    private function renderOrHide(Profile $profile): View|Response
    {
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            abort(404);
        }

        return response()
            ->view('public.profile-stub', [
                'profile' => $profile,
                'canonicalUrl' => $this->profileUrls->canonicalPublicUrl($profile),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

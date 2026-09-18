<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileUrlService;
use App\Services\PublicProfilePresentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicProfileUrlController extends Controller
{
    public function __construct(
        private ProfileUrlService $profileUrls,
        private PublicProfilePresentationService $presentation,
    ) {}

    /**
     * Resolve /p/{slug}: redirect historical URLs; render published profiles only.
     */
    public function show(Request $request, string $slug): View|RedirectResponse|Response
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '' || str_contains($slug, '/') || str_contains($slug, '\\') || str_contains($slug, '..')) {
            abort(404);
        }

        $profile = Profile::query()
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->first();

        if ($profile) {
            return $this->renderOrHide($request, $profile);
        }

        $redirect = $this->profileUrls->findActiveRedirect($normalized);
        if ($redirect === null) {
            abort(404);
        }

        $target = $redirect->redirectable;
        if (! $target instanceof Profile) {
            abort(404);
        }

        if ((int) $redirect->redirectable_id !== (int) $target->id) {
            abort(404);
        }

        if (! filled($target->slug)) {
            abort(404);
        }

        if (! $this->profileUrls->isPubliclyVisible($target)) {
            abort(404);
        }

        return redirect()->route('profiles.public', [
            'slug' => $target->slug,
            'lang' => $request->query('lang'),
        ], 301);
    }

    private function renderOrHide(Request $request, Profile $profile): View|Response
    {
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            abort(404);
        }

        $lang = (string) $request->query('lang', 'en');
        $data = $this->presentation->present($profile, $lang);

        return response()
            ->view('public.profile', $data)
            ->header('Cache-Control', 'public, max-age=60');
    }
}

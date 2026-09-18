<?php

namespace App\Http\Controllers;

use App\Models\InMemoriamEditorialContent;
use App\Services\InMemoriamLifecycleService;
use App\Services\InMemoriamMediaService;
use App\Services\InMemoriamUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicInMemoriamController extends Controller
{
    public function __construct(
        private InMemoriamUrlService $urls,
        private InMemoriamLifecycleService $lifecycle,
        private InMemoriamMediaService $media,
    ) {}

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $normalized = strtolower(trim($slug));
        $profile = $this->urls->findByPublicSlug($normalized);

        if ($profile === null || ! $this->lifecycle->isPubliclyVisible($profile)) {
            abort(404);
        }

        if (filled($profile->slug) && strtolower((string) $profile->slug) !== $normalized) {
            return redirect()->route('in-memoriam.show', ['slug' => $profile->slug], 301);
        }

        $language = strtolower((string) $request->query('lang', 'en'));
        if (! in_array($language, ['en', 'ml'], true)) {
            $language = 'en';
        }

        $editorials = $profile->editorialContents()
            ->where('status', InMemoriamEditorialContent::STATUS_APPROVED)
            ->orderByDesc('version_number')
            ->get()
            ->groupBy('language');

        $english = $editorials->get(InMemoriamEditorialContent::LANGUAGE_EN)?->first();
        $malayalam = $editorials->get(InMemoriamEditorialContent::LANGUAGE_ML)?->first();

        $active = $language === 'ml' && $malayalam ? $malayalam : $english;
        if ($language === 'ml' && ! $malayalam) {
            $language = 'en';
            $active = $english;
        }

        $photos = collect($this->media->approvedPublicPhotographs($profile));
        $primary = $this->media->primaryApprovedPhotograph($profile);
        $profile->loadMissing(['publicOffices']);

        $commissionerLabel = null;
        if ($profile->commissioner_display_consent && filled($profile->commissioner_contact_name)) {
            $commissionerLabel = (string) $profile->commissioner_contact_name;
            if (filled($profile->commissioner_relation)) {
                $commissionerLabel .= ' ('.$profile->commissioner_relation.')';
            }
        }

        return view('in-memoriam.show', [
            'profile' => $profile,
            'displayName' => $profile->displayName(),
            'profession' => $profile->profession,
            'headline' => $profile->bio_headline,
            'photo' => $primary,
            'photos' => $photos,
            'publicOffices' => $profile->publicOffices,
            'english' => $english,
            'malayalam' => $malayalam,
            'activeEditorial' => $active,
            'language' => $language,
            'canonicalUrl' => route('in-memoriam.show', ['slug' => $profile->slug]),
            'commissionerLabel' => $commissionerLabel,
            'hostingStartsOn' => $profile->hosting_starts_on,
            'hostingEndsOn' => $profile->hosting_ends_on,
        ]);
    }
}

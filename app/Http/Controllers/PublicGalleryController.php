<?php

namespace App\Http\Controllers;

use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicGalleryController extends Controller
{
    public function __construct(
        private PublicProfileSearchService $search,
        private PublicProfilePresentationService $presentation,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        $profiles = $this->search->gallery($filters);

        return view('public.gallery', [
            'profiles' => $profiles,
            'presentation' => $this->presentation,
            'q' => '',
            'filters' => $filters,
            'districts' => $this->search->districtOptions(),
            'states' => $this->search->stateOptions(),
            'mode' => 'gallery',
            'nav' => 'gallery',
        ]);
    }

    /**
     * @return array{district_id: int|null, state: string|null, country: string|null}
     */
    private function filtersFromRequest(Request $request): array
    {
        $districtId = $request->integer('district_id') ?: null;
        $state = trim((string) $request->query('state', ''));
        $country = trim((string) $request->query('country', ''));

        return [
            'district_id' => $districtId,
            'state' => $state !== '' ? mb_substr($state, 0, 128) : null,
            'country' => $country !== '' ? mb_substr($country, 0, 64) : null,
        ];
    }
}

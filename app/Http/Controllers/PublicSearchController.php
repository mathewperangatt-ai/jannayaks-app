<?php

namespace App\Http\Controllers;

use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSearchController extends Controller
{
    public function __construct(
        private PublicProfileSearchService $search,
        private PublicProfilePresentationService $presentation,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $q = mb_substr($q, 0, 200);

        $filters = [
            'district_id' => $request->integer('district_id') ?: null,
            'state' => ($s = trim((string) $request->query('state', ''))) !== '' ? mb_substr($s, 0, 128) : null,
            'country' => ($c = trim((string) $request->query('country', ''))) !== '' ? mb_substr($c, 0, 64) : null,
        ];

        $profiles = $q === ''
            ? $this->search->gallery($filters)
            : $this->search->search($q, $filters);

        return view('public.search', [
            'profiles' => $profiles,
            'presentation' => $this->presentation,
            'q' => $q,
            'filters' => $filters,
            'districts' => $this->search->districtOptions(),
            'states' => $this->search->stateOptions(),
            'mode' => 'search',
            'nav' => 'search',
        ]);
    }
}

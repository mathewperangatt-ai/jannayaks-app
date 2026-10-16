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

        $profiles = $q === ''
            ? $this->search->gallery()
            : $this->search->search($q);

        return view('public.search', [
            'profiles' => $profiles,
            'presentation' => $this->presentation,
            'q' => $q,
            'mode' => 'search',
            'nav' => 'search',
        ]);
    }
}

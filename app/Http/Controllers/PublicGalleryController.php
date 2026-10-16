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
        $profiles = $this->search->gallery();

        return view('public.gallery', [
            'profiles' => $profiles,
            'presentation' => $this->presentation,
            'q' => '',
            'mode' => 'gallery',
            'nav' => 'gallery',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * "Recommend Someone You May Know".
 *
 * The page only prepares a draft for the visitor's own email application.
 * Jannayaks does not send the message or store the recommendation.
 */
class RecommendationController extends Controller
{
    public function show(): View
    {
        return view('public.recommend');
    }
}

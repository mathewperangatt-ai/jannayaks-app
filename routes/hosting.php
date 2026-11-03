<?php

use App\Http\Controllers\Hosting\HostingIntegrationController;
use Illuminate\Support\Facades\Route;

/*
| IconsHerald Jannayaks Hosting — signed server-to-server read API.
| CSRF and session middleware are not applied; HMAC auth is required.
*/

Route::get('/profiles/{slug}', [HostingIntegrationController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9.\-]*')
    ->name('hosting.integration.profile');

Route::get('/sitemap-profiles', [HostingIntegrationController::class, 'sitemap'])
    ->name('hosting.integration.sitemap');

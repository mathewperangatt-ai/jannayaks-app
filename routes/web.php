<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MobileOtpController;
use App\Http\Controllers\CustomerProfilePreviewController;
use App\Http\Controllers\InMemoriamLandingController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OnlineInterviewController;
use App\Http\Controllers\PaymentDocumentController;
use App\Http\Controllers\ProfileExternalVideoLinkController;
use App\Http\Controllers\ProfileMediaController;
use App\Http\Controllers\ProfileUrlController;
use App\Http\Controllers\PublicGalleryController;
use App\Http\Controllers\PublicInMemoriamController;
use App\Http\Controllers\PublicInMemoriamMediaController;
use App\Http\Controllers\PublicProfileMediaController;
use App\Http\Controllers\PublicProfileUrlController;
use App\Http\Controllers\PublicSearchController;
use App\Http\Controllers\RazorpayCallbackController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\Staff\ProfileMediaPreviewController;
use App\Http\Controllers\Staff\SourceMaterialDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/gallery', [PublicGalleryController::class, 'index'])->name('gallery.index');
Route::get('/search', [PublicSearchController::class, 'index'])->name('search.index');

Route::get('/in-memoriam', InMemoriamLandingController::class)->name('in-memoriam.index');
Route::get('/in-memoriam/{slug}', [PublicInMemoriamController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-]*')
    ->name('in-memoriam.show');
Route::get('/in-memoriam/{slug}/photo/{media}', [PublicInMemoriamMediaController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-]*')
    ->whereNumber('media')
    ->name('in-memoriam.photo');

Route::get('/p/{slug}', [PublicProfileUrlController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-]*')
    ->name('profiles.public');

Route::get('/p/{profile}/photo/{media}', [PublicProfileMediaController::class, 'show'])
    ->whereNumber('profile')
    ->whereNumber('media')
    ->name('profiles.public.photo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');

    Route::get('/auth/otp', [MobileOtpController::class, 'showRequestForm'])->name('auth.otp.request.show');
    Route::post('/auth/otp', [MobileOtpController::class, 'send'])->middleware('throttle:10,1')->name('auth.otp.send');
    Route::get('/auth/otp/verify', [MobileOtpController::class, 'showVerifyForm'])->name('auth.otp.verify.show');
    Route::post('/auth/otp/verify', [MobileOtpController::class, 'verify'])->middleware('throttle:20,1')->name('auth.otp.verify');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/source-materials/{sourceMaterial}/download', SourceMaterialDownloadController::class)
        ->name('source-materials.download');
    Route::get('/profile-media/{media}/preview', ProfileMediaPreviewController::class)
        ->whereNumber('media')
        ->name('profile-media.preview');
});

Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
Route::post('/apply/intent', [ApplicationController::class, 'storeIntent'])->middleware('throttle:20,1')->name('apply.intent');

Route::middleware(['auth', 'verified.or.mobile'])->group(function () {
    Route::get('/apply/continue', [ApplicationController::class, 'continueFromIntent'])->name('apply.continue');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');

    Route::get('/applications/{application}', [ApplicationController::class, 'showOwn'])->name('applications.show');
    Route::get('/applications/{application}/upload', [ApplicationController::class, 'uploadsShow'])->name('applications.upload.show');
    Route::post('/applications/{application}/upload', [ApplicationController::class, 'uploadMaterial'])->name('applications.upload.material');

    Route::get('/applications/{application}/interview', [OnlineInterviewController::class, 'show'])->name('online-interview.show');
    Route::match(['put', 'patch', 'post'], '/applications/{application}/interview/save', [OnlineInterviewController::class, 'save'])->name('online-interview.save');
    Route::post('/applications/{application}/interview/submit', [OnlineInterviewController::class, 'submit'])->name('online-interview.submit');

    Route::get('/applications/{application}/payment', [ApplicationController::class, 'showPayment'])->name('applications.payment');
    Route::post('/applications/{application}/payment/initiate', [ApplicationController::class, 'initiatePayment'])->name('applications.payment.initiate');

    Route::get('/applications/{application}/preview', [CustomerProfilePreviewController::class, 'show'])->name('applications.preview');
    Route::post('/applications/{application}/preview/revision', [CustomerProfilePreviewController::class, 'requestRevision'])
        ->middleware('throttle:10,1')
        ->name('applications.preview.revision');
    Route::post('/applications/{application}/preview/approve', [CustomerProfilePreviewController::class, 'approve'])
        ->middleware('throttle:10,1')
        ->name('applications.preview.approve');

    Route::get('/applications/{application}/profile-url', [ProfileUrlController::class, 'show'])
        ->name('applications.profile-url');
    Route::post('/applications/{application}/profile-url', [ProfileUrlController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('applications.profile-url.update');
    Route::get('/applications/{application}/profile-url/availability', [ProfileUrlController::class, 'availability'])
        ->middleware('throttle:30,1')
        ->name('applications.profile-url.availability');
    Route::get('/applications/{application}/profile-qr', [ProfileUrlController::class, 'qr'])
        ->middleware('throttle:30,1')
        ->name('applications.profile-qr');

    Route::get('/applications/{application}/media', [ProfileMediaController::class, 'show'])
        ->name('applications.media');
    Route::post('/applications/{application}/media', [ProfileMediaController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('applications.media.store');
    Route::post('/applications/{application}/media/{media}/primary', [ProfileMediaController::class, 'setPrimary'])
        ->middleware('throttle:30,1')
        ->whereNumber('media')
        ->name('applications.media.primary');
    Route::delete('/applications/{application}/media/{media}', [ProfileMediaController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->whereNumber('media')
        ->name('applications.media.destroy');
    Route::post('/applications/{application}/media/video-links', [ProfileExternalVideoLinkController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('applications.media.video.store');

    Route::get('/payments/{payment}/receipt', [PaymentDocumentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/{payment}/tax-invoice', [PaymentDocumentController::class, 'taxInvoice'])->name('payments.tax-invoice');
    Route::get('/payments/{payment}/credit-note', [PaymentDocumentController::class, 'creditNote'])->name('payments.credit-note');

    Route::get('/profiles/{profile}/membership', [MembershipController::class, 'show'])
        ->name('membership.show');
    Route::post('/profiles/{profile}/membership/renew', [MembershipController::class, 'renew'])
        ->middleware('throttle:10,1')
        ->name('membership.renew');
});

Route::get('/payments/razorpay/callback', [RazorpayCallbackController::class, 'show'])->name('payments.razorpay.callback');

Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/payments/razorpay/webhook', [RazorpayWebhookController::class, 'handle'])->name('payments.razorpay.webhook');
});

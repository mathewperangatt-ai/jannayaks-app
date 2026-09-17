<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MobileOtpController;
use App\Http\Controllers\OnlineInterviewController;
use App\Http\Controllers\RazorpayCallbackController;
use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

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
});

Route::get('/payments/razorpay/callback', [RazorpayCallbackController::class, 'show'])->name('payments.razorpay.callback');

Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/payments/razorpay/webhook', [RazorpayWebhookController::class, 'handle'])->name('payments.razorpay.webhook');
});

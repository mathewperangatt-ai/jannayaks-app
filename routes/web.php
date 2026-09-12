<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\OnlineInterviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified.or.mobile'])->group(function () {
    Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');

    Route::get('/applications/{application}', [ApplicationController::class, 'showOwn'])->name('applications.show');
    Route::get('/applications/{application}/upload', [ApplicationController::class, 'uploadsShow'])->name('applications.upload.show');
    Route::post('/applications/{application}/upload', [ApplicationController::class, 'uploadMaterial'])->name('applications.upload.material');

    Route::get('/applications/{application}/interview', [OnlineInterviewController::class, 'show'])->name('online-interview.show');
    Route::match(['put', 'patch', 'post'], '/applications/{application}/interview/save', [OnlineInterviewController::class, 'save'])->name('online-interview.save');
    Route::post('/applications/{application}/interview/submit', [OnlineInterviewController::class, 'submit'])->name('online-interview.submit');
});

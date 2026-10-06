<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Student\HelpRequestController;
use App\Http\Controllers\Tutor\RequestQueueController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Help requests — students (SRS: REQ-01 – REQ-11)
    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('/', [HelpRequestController::class, 'index'])->name('index');
        Route::get('/new', [HelpRequestController::class, 'create'])->name('create');
        Route::post('/', [HelpRequestController::class, 'store'])->name('store');
        Route::get('/{helpRequest}', [HelpRequestController::class, 'show'])->name('show');
        Route::post('/{helpRequest}/cancel', [HelpRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{helpRequest}/confirm', [HelpRequestController::class, 'confirm'])->name('confirm');
        Route::post('/{helpRequest}/reopen', [HelpRequestController::class, 'reopen'])->name('reopen');
    });

    // Tutor queue
    Route::prefix('tutor')->name('tutor.')->group(function () {
        Route::get('/queue', [RequestQueueController::class, 'index'])->name('queue');
        Route::post('/availability', [RequestQueueController::class, 'toggleAvailability'])->name('availability');
        Route::post('/requests/{helpRequest}/accept', [RequestQueueController::class, 'accept'])->name('requests.accept');
        Route::post('/requests/{helpRequest}/decline', [RequestQueueController::class, 'decline'])->name('requests.decline');
        Route::post('/requests/{helpRequest}/resolve', [RequestQueueController::class, 'resolve'])->name('requests.resolve');
    });

    // Onboarding wizard (SRS: ONB-01 – ONB-10)
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [OnboardingController::class, 'show'])->name('show');
        Route::post('/', [OnboardingController::class, 'store'])->name('store');
        Route::post('/back', [OnboardingController::class, 'back'])->name('back');
        Route::get('/subjects', [OnboardingController::class, 'subjects'])->name('subjects');
        Route::post('/subjects', [OnboardingController::class, 'storeSubjects'])->name('subjects.store');
        Route::post('/restart', [OnboardingController::class, 'restart'])->name('restart');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

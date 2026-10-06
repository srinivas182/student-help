<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Moderation\ModerationController;
use App\Http\Controllers\RatingController;
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

// Guardian consent — public, token-authenticated (SRS: CON-02)
Route::get('/consent/{token}', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent/{token}', [ConsentController::class, 'decide'])->name('consent.decide');

Route::middleware(['auth', 'verified'])->group(function () {
    // Notifications (SRS: NOT-01 – NOT-04)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('readAll');
        Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences');
    });

    Route::post('/consent/resend', [ConsentController::class, 'resend'])->name('consent.resend');

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

    // Conversations, ratings and reporting (SRS: MSG-*, RAT-*)
    Route::prefix('conversations')->name('conversations.')->group(function () {
        Route::get('/{helpRequest}', [ConversationController::class, 'show'])->name('show');
        Route::get('/{helpRequest}/poll', [ConversationController::class, 'poll'])->name('poll');
        Route::post('/{helpRequest}/messages', [ConversationController::class, 'store'])->name('store');
        Route::post('/{helpRequest}/messages/{message}/report', [ConversationController::class, 'report'])->name('report');
    });

    Route::post('/requests/{helpRequest}/rating', [RatingController::class, 'store'])->name('ratings.store');

    // Moderation (staff only)
    Route::middleware('staff')->prefix('moderation')->name('moderation.')->group(function () {
        Route::get('/', [ModerationController::class, 'index'])->name('index');
        Route::get('/conversations/{helpRequest}', [ModerationController::class, 'conversation'])->name('conversation');
        Route::post('/reports/{report}/resolve', [ModerationController::class, 'resolve'])->name('resolve');
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

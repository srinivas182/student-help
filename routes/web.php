<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AssistantSettingsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CoverageController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ResourceReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolLinkController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Moderation\ModerationController;
use App\Http\Controllers\PortalLandingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\VoiceNoteController;
use App\Http\Controllers\Moderation\StudyGroupReviewController;
use App\Http\Controllers\Moderation\VoiceReviewController;
use App\Http\Controllers\Student\HelpRequestController;
use App\Http\Controllers\Tutor\RequestQueueController;
use App\Http\Controllers\Tutor\TeacherDashboardController;
use App\Http\Controllers\Tutor\TutorProfileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', PortalLandingController::class)->name('home');

// Staff invitations — public, token-authenticated (SRS: AUTH-08)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

// Guardian consent — public, token-authenticated (SRS: CON-02)
Route::get('/consent/{token}', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent/{token}', [ConsentController::class, 'decide'])->name('consent.decide');

Route::middleware(['auth', 'verified', 'portal'])->group(function () {
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

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');

    // Classes hosted by teachers (own group or linked to a school)
    Route::prefix('classes')->name('classrooms.')->group(function () {
        Route::get('/', [ClassroomController::class, 'index'])->name('index');
        Route::post('/', [ClassroomController::class, 'store'])->name('store');
        Route::post('/join', [ClassroomController::class, 'join'])->name('join');
        Route::get('/{classroom}', [ClassroomController::class, 'show'])->name('show');
        Route::post('/{classroom}/leave', [ClassroomController::class, 'leave'])->name('leave');
        Route::post('/{classroom}/members/{user}/remove', [ClassroomController::class, 'removeMember'])->name('members.remove');
        Route::post('/{classroom}/code', [ClassroomController::class, 'rotateCode'])->name('code');
        Route::post('/{classroom}/posts', [ClassroomController::class, 'post'])->name('posts.store');
        Route::post('/{classroom}/posts/{post}/complete', [ClassroomController::class, 'complete'])->name('posts.complete');
    });

    // Study material: notes, past papers, solutions (SRS: RES-01 – RES-08)
    Route::prefix('resources')->name('resources.')->group(function () {
        Route::get('/', [ResourceController::class, 'index'])->name('index');
        Route::get('/mine', [ResourceController::class, 'mine'])->name('mine');
        Route::post('/', [ResourceController::class, 'store'])->name('store');
        Route::get('/{resource}', [ResourceController::class, 'show'])->name('show');
        Route::get('/{resource}/download', [ResourceController::class, 'download'])->name('download');
    });

    // AI Tutor: what students use
    Route::prefix('learn')->name('learn.')->group(function () {
        Route::get('/', [LearnController::class, 'index'])->name('index');
        Route::post('/language', [LearnController::class, 'setLanguage'])->name('language');
        Route::get('/reviews', [AssessmentController::class, 'reviews'])->name('reviews');
        Route::get('/{topic}', [LearnController::class, 'show'])->name('topic');
        Route::post('/{topic}/progress', [LearnController::class, 'saveProgress'])->name('progress');
    });

    // Assessments, mastery and spaced review
    Route::prefix('assessment')->name('assessment.')->group(function () {
        Route::get('/{topic}', [AssessmentController::class, 'index'])->name('index');
        Route::get('/{topic}/result/{attempt}', [AssessmentController::class, 'result'])->name('result');
        Route::get('/{topic}/{level}', [AssessmentController::class, 'start'])->name('start');
        Route::post('/{topic}/{level}', [AssessmentController::class, 'submit'])->name('submit');
    });

    // AI Tutor review workspace — for content reviewers, not administrators
    Route::middleware('can.do:topics.review')->prefix('review')->name('review.')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/{version}', [ReviewController::class, 'show'])->name('show');
        Route::put('/{version}/segments/{index}', [ReviewController::class, 'updateSegment'])->name('segments.update');
        Route::delete('/{version}/segments/{index}', [ReviewController::class, 'removeSegment'])->name('segments.remove');
        Route::put('/questions/{question}', [ReviewController::class, 'updateQuestion'])->name('questions.update');
        Route::delete('/questions/{question}', [ReviewController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::post('/{version}/publish', [ReviewController::class, 'publish'])->name('publish');
        Route::post('/{version}/reject', [ReviewController::class, 'reject'])->name('reject');
        Route::post('/{version}/unpublish', [ReviewController::class, 'unpublish'])->name('unpublish');
    });

    // AI study assistant
    Route::prefix('assistant')->name('assistant.')->group(function () {
        Route::get('/', [AssistantController::class, 'index'])->name('index');
        Route::post('/ask', [AssistantController::class, 'ask'])->name('ask');
        Route::post('/{answer}/escalate', [AssistantController::class, 'escalate'])->name('escalate');
        Route::post('/{answer}/feedback', [AssistantController::class, 'feedback'])->name('feedback');
    });

    // Subject discussion boards (SRS: COM-01 – COM-06)
    Route::prefix('community')->name('community.')->group(function () {
        Route::get('/', [CommunityController::class, 'index'])->name('index');
        Route::post('/', [CommunityController::class, 'store'])->name('store');
        Route::get('/{post}', [CommunityController::class, 'show'])->name('show');
        Route::post('/{post}/replies', [CommunityController::class, 'reply'])->name('reply');
        Route::post('/{post}/accept', [CommunityController::class, 'accept'])->name('accept');
        Route::post('/{post}/vote', [CommunityController::class, 'vote'])->name('vote');
        Route::post('/{post}/report', [CommunityController::class, 'report'])->name('report');
    });

    // Progress and recognition (SRS: PRG-01 – PRG-03)
    Route::get('/progress', ProgressController::class)->name('progress');
    Route::get('/progress/certificate', [ProgressController::class, 'certificate'])->name('progress.certificate');

    // Student-created study groups
    Route::prefix('study-groups')->name('studyGroups.')->group(function () {
        Route::get('/', [StudyGroupController::class, 'index'])->name('index');
        Route::post('/', [StudyGroupController::class, 'store'])->name('store');
        Route::post('/join', [StudyGroupController::class, 'join'])->name('join');
        Route::get('/{group}', [StudyGroupController::class, 'show'])->name('show');
        Route::get('/{group}/poll', [StudyGroupController::class, 'poll'])->name('poll');
        Route::post('/{group}/join', [StudyGroupController::class, 'joinById'])->name('joinById');
        Route::post('/{group}/messages', [StudyGroupController::class, 'post'])->name('post');
        Route::post('/{group}/messages/{message}/report', [StudyGroupController::class, 'report'])->name('report');
        Route::post('/{group}/leave', [StudyGroupController::class, 'leave'])->name('leave');
        Route::post('/{group}/members/{user}/remove', [StudyGroupController::class, 'removeMember'])->name('members.remove');
    });

    // Voice note lessons
    Route::prefix('voice-notes')->name('voiceNotes.')->group(function () {
        Route::post('/', [VoiceNoteController::class, 'store'])->name('store');
        Route::get('/{voiceNote}/play', [VoiceNoteController::class, 'play'])->name('play');
        Route::delete('/{voiceNote}', [VoiceNoteController::class, 'destroy'])->name('destroy');
    });

    // Conversations, ratings and reporting (SRS: MSG-*, RAT-*)
    Route::prefix('conversations')->name('conversations.')->group(function () {
        Route::get('/{helpRequest}', [ConversationController::class, 'show'])->name('show');
        Route::get('/{helpRequest}/poll', [ConversationController::class, 'poll'])->name('poll');
        Route::post('/{helpRequest}/messages', [ConversationController::class, 'store'])->name('store');
        Route::post('/{helpRequest}/messages/{message}/report', [ConversationController::class, 'report'])->name('report');
    });

    Route::post('/requests/{helpRequest}/rating', [RatingController::class, 'store'])->name('ratings.store');

    // Admin: tutor verification and subject coverage (SRS: TUT-03, D-08)
    Route::middleware('staff')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
        Route::get('/verification/{tutorProfile}', [VerificationController::class, 'show'])->name('verification.show');
        Route::get('/documents/{document}', [VerificationController::class, 'document'])->name('documents.show');
        Route::post('/verification/{tutorProfile}/approve', [VerificationController::class, 'approve'])->name('verification.approve');
        Route::post('/verification/{tutorProfile}/reject', [VerificationController::class, 'reject'])->name('verification.reject');
        Route::post('/verification/{tutorProfile}/suspend', [VerificationController::class, 'suspend'])->name('verification.suspend');
        Route::get('/coverage', CoverageController::class)->name('coverage');

        // Operational dashboard, users, curriculum, settings, audit (ADM-01 – ADM-06)
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/reinstate', [UserController::class, 'reinstate'])->name('users.reinstate');
        Route::post('/invitations', [UserController::class, 'invite'])->name('invitations.send');
        Route::delete('/invitations/{invitation}', [UserController::class, 'revokeInvitation'])->name('invitations.revoke');

        Route::get('/curriculum', [CurriculumController::class, 'index'])->name('curriculum.index');
        Route::post('/curriculum', [CurriculumController::class, 'store'])->name('curriculum.store');
        Route::put('/curriculum/{curriculumItem}', [CurriculumController::class, 'update'])->name('curriculum.update');
        Route::post('/curriculum/{curriculumItem}/toggle', [CurriculumController::class, 'toggle'])->name('curriculum.toggle');
        Route::post('/curriculum/reorder', [CurriculumController::class, 'reorder'])->name('curriculum.reorder');
        Route::post('/curriculum/import', [CurriculumController::class, 'import'])->name('curriculum.import');

        // Roles, permissions and reviewer scopes
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('/roles/assign', [RoleController::class, 'assign'])->name('roles.assign');
        Route::delete('/roles/{role}/users/{user}', [RoleController::class, 'revoke'])->name('roles.revoke')->scopeBindings();
        Route::post('/reviewer-scopes', [RoleController::class, 'addScope'])->name('roles.scopes.add');
        Route::delete('/reviewer-scopes/{scope}', [RoleController::class, 'removeScope'])->name('roles.scopes.remove');
        Route::get('/access-activity', [RoleController::class, 'activity'])->name('roles.activity');

        // AI Tutor: curriculum lessons generated once, used by every student
        Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
        Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
        Route::get('/topics/{topic}', [TopicController::class, 'show'])->name('topics.show');
        Route::post('/topics/{topic}/sources', [TopicController::class, 'addSource'])->name('topics.sources');
        Route::post('/topics/{topic}/generate', [TopicController::class, 'generate'])->name('topics.generate');
        Route::delete('/sources/{source}', [TopicController::class, 'destroySource'])->name('topics.sources.destroy');

        Route::get('/assistant', [AssistantSettingsController::class, 'edit'])->name('assistant');
        Route::put('/assistant', [AssistantSettingsController::class, 'update'])->name('assistant.update');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/audit', AuditLogController::class)->name('audit');

        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/school-links', [SchoolLinkController::class, 'index'])->name('schoolLinks.index');
        Route::post('/school-links/{classroom}/approve', [SchoolLinkController::class, 'approve'])->name('schoolLinks.approve');
        Route::post('/school-links/{classroom}/reject', [SchoolLinkController::class, 'reject'])->name('schoolLinks.reject');

        Route::get('/resources', [ResourceReviewController::class, 'index'])->name('resources.index');
        Route::post('/resources/{resource}/approve', [ResourceReviewController::class, 'approve'])->name('resources.approve');
        Route::post('/resources/{resource}/reject', [ResourceReviewController::class, 'reject'])->name('resources.reject');
        Route::post('/resources/{resource}/unpublish', [ResourceReviewController::class, 'unpublish'])->name('resources.unpublish');
    });

    // Moderation (staff only)
    Route::middleware('staff')->prefix('moderation')->name('moderation.')->group(function () {
        Route::get('/', [ModerationController::class, 'index'])->name('index');
        Route::get('/conversations/{helpRequest}', [ModerationController::class, 'conversation'])->name('conversation');
        Route::post('/reports/{report}/resolve', [ModerationController::class, 'resolve'])->name('resolve');
        Route::get('/study-groups', [StudyGroupReviewController::class, 'index'])->name('groups');
        Route::get('/study-groups/{group}', [StudyGroupReviewController::class, 'show'])->name('groups.show');
        Route::post('/study-groups/{group}/lock', [StudyGroupReviewController::class, 'lock'])->name('groups.lock');
        Route::post('/study-groups/{group}/unlock', [StudyGroupReviewController::class, 'unlock'])->name('groups.unlock');
        Route::post('/group-messages/{message}/remove', [StudyGroupReviewController::class, 'removeMessage'])->name('groups.message.remove');

        Route::get('/voice-notes', [VoiceReviewController::class, 'index'])->name('voice');
        Route::post('/voice-notes/{voiceNote}/clear', [VoiceReviewController::class, 'clear'])->name('voice.clear');
        Route::delete('/voice-notes/{voiceNote}', [VoiceReviewController::class, 'remove'])->name('voice.remove');
    });

    // Tutor queue
    Route::prefix('tutor')->name('tutor.')->group(function () {
        Route::get('/', TeacherDashboardController::class)->name('home');
        Route::get('/queue', [RequestQueueController::class, 'index'])->name('queue');
        Route::post('/availability', [RequestQueueController::class, 'toggleAvailability'])->name('availability');
        Route::post('/requests/{helpRequest}/accept', [RequestQueueController::class, 'accept'])->name('requests.accept');
        Route::post('/requests/{helpRequest}/decline', [RequestQueueController::class, 'decline'])->name('requests.decline');
        Route::post('/requests/{helpRequest}/resolve', [RequestQueueController::class, 'resolve'])->name('requests.resolve');

        // Tutor profile and verification (SRS: TUT-01 – TUT-04)
        Route::get('/profile', [TutorProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [TutorProfileController::class, 'update'])->name('profile.update');
        Route::post('/documents', [TutorProfileController::class, 'uploadDocument'])->name('documents.upload');
        Route::post('/submit', [TutorProfileController::class, 'submit'])->name('submit');
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

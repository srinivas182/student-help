<?php

use App\Domains\Content\Models\Announcement;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    config([
        'portals.student.host' => 'student-help.rightally.io',
        'portals.teacher.host' => 'teacher-help.rightally.io',
        'portals.allow_shared_host' => true,
    ]);

    $this->grade = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)->where('name', 'Grade 11')->first();
    $this->subject = $this->grade->children()->ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    $profile = TutorProfile::create([
        'user_id' => $this->teacher->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
        'resolved_count' => 12,
        'average_rating' => 4.7,
        'ratings_count' => 9,
    ]);
    $profile->subjects()->attach($this->subject->id);
    $this->teacher->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $this->student->academicContext()->attach($this->grade->id, ['role' => 'context']);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
});

it('shows each portal its own landing page with a link to the other door', function () {
    $this->get('http://student-help.rightally.io/')
        ->assertInertia(fn ($page) => $page
            ->component('Public/StudentLanding')
            ->where('brand.key', 'student')
            ->where('brand.otherUrl', 'http://teacher-help.rightally.io/')
            ->has('stats.tutors'));

    $this->get('http://teacher-help.rightally.io/')
        ->assertInertia(fn ($page) => $page
            ->component('Public/TeacherLanding')
            ->where('brand.key', 'teacher')
            ->where('brand.otherUrl', 'http://student-help.rightally.io/')
            ->has('stats.openRequests'));
});

it('sends a signed-in user from the landing page to their own portal home', function () {
    $this->actingAs($this->teacher)->get('http://teacher-help.rightally.io/')->assertRedirect(route('tutor.home'));
    $this->actingAs($this->student)->get('http://student-help.rightally.io/')->assertRedirect(route('dashboard'));
});

it('gives teachers a dashboard showing waiting students and their impact', function () {
    $this->actingAs($this->teacher)
        ->get(route('tutor.home'))
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Dashboard')
            ->where('impact.resolved', 12)
            ->where('verification.isVerified', true)
            ->has('waiting')
            ->has('active'));
});

it('records every cross-portal redirect so DX can see wrong links', function () {
    $this->actingAs($this->student)->get('http://teacher-help.rightally.io/dashboard')->assertRedirect();

    $row = DB::table('portal_redirects')->first();

    expect($row->from_portal)->toBe('teacher')
        ->and($row->to_portal)->toBe('student')
        ->and($row->role)->toBe('student');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('portals.studentsAtTeacherDoor', 1));
});

it('publishes an announcement to the audience it targets', function () {
    $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
        'title' => 'Exam timetable released',
        'body' => 'The November timetable is now available from your school.',
        'priority' => 'important',
        'roles' => ['student'],
        'curriculum_item_ids' => [$this->grade->id],
    ])->assertRedirect();

    Notification::assertSentTo($this->student, AnnouncementPublished::class);
    Notification::assertNotSentTo($this->teacher, AnnouncementPublished::class);

    $this->actingAs($this->student)
        ->get(route('announcements.index'))
        ->assertInertia(fn ($page) => $page->has('announcements', 1));
});

it('hides an announcement from anyone outside its targeting', function () {
    $otherGrade = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)->where('name', 'Grade 8')->first();

    Announcement::create([
        'author_id' => $this->admin->id,
        'title' => 'Grade 8 orientation',
        'body' => 'Welcome session for Grade 8 learners.',
        'priority' => 'normal',
        'targeting' => ['roles' => ['student'], 'curriculum_item_ids' => [$otherGrade->id]],
        'publish_at' => now()->subHour(),
    ]);

    $this->actingAs($this->student)
        ->get(route('announcements.index'))
        ->assertInertia(fn ($page) => $page->has('announcements', 0));
});

it('does not show a scheduled announcement before its time', function () {
    Announcement::create([
        'author_id' => $this->admin->id,
        'title' => 'Results day',
        'body' => 'Results are published next week.',
        'priority' => 'normal',
        'targeting' => ['roles' => [], 'curriculum_item_ids' => []],
        'publish_at' => now()->addWeek(),
    ]);

    $this->actingAs($this->student)
        ->get(route('announcements.index'))
        ->assertInertia(fn ($page) => $page->has('announcements', 0));
});

it('keeps announcement creation to staff', function () {
    $this->actingAs($this->teacher)->post(route('admin.announcements.store'), [
        'title' => 'Not allowed',
        'body' => 'Teachers cannot broadcast to everyone.',
        'priority' => 'normal',
    ])->assertForbidden();
});

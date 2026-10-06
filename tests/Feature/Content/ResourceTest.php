<?php

use App\Domains\Content\Models\Resource;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->otherSubject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();

    $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    $profile = TutorProfile::create([
        'user_id' => $this->teacher->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
    ]);
    $profile->subjects()->attach($this->subject->id);
    $this->teacher->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
});

function share(User $uploader, array $overrides = []): Resource
{
    test()->actingAs($uploader)->post(route('resources.store'), array_merge([
        'title' => 'Trigonometry worked examples',
        'description' => 'Ten worked examples covering identities and proofs.',
        'resource_type' => 'notes',
        'file' => UploadedFile::fake()->create('notes.pdf', 400, 'application/pdf'),
        'curriculum_item_ids' => [test()->subject->id],
        'rights_declared' => true,
    ], $overrides));

    return Resource::latest('id')->first();
}

it('holds a teacher upload for approval and publishes an admin upload immediately', function () {
    $teacherResource = share($this->teacher);
    expect($teacherResource->status)->toBe(Resource::STATUS_PENDING);

    $adminResource = share($this->admin, ['title' => 'Official past paper']);
    expect($adminResource->status)->toBe(Resource::STATUS_PUBLISHED);
});

it('requires the copyright declaration', function () {
    $this->actingAs($this->teacher)->post(route('resources.store'), [
        'title' => 'Scanned textbook chapter',
        'resource_type' => 'course_material',
        'file' => UploadedFile::fake()->create('chapter.pdf', 200, 'application/pdf'),
        'curriculum_item_ids' => [$this->subject->id],
        'rights_declared' => false,
    ])->assertSessionHasErrors('rights_declared');

    expect(Resource::count())->toBe(0);
});

it('requires a file or a link, and at least one subject tag', function () {
    $this->actingAs($this->teacher)->post(route('resources.store'), [
        'title' => 'Nothing attached',
        'resource_type' => 'notes',
        'curriculum_item_ids' => [$this->subject->id],
        'rights_declared' => true,
    ])->assertSessionHasErrors('external_url');

    $this->actingAs($this->teacher)->post(route('resources.store'), [
        'title' => 'Untagged notes',
        'resource_type' => 'notes',
        'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        'curriculum_item_ids' => [],
        'rights_declared' => true,
    ])->assertSessionHasErrors('curriculum_item_ids');
});

it('does not let an unverified teacher share material', function () {
    $pending = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    TutorProfile::create(['user_id' => $pending->id, 'verification_status' => TutorProfile::STATUS_PENDING]);

    $this->actingAs($pending)->post(route('resources.store'), [
        'title' => 'Not verified yet',
        'resource_type' => 'notes',
        'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        'curriculum_item_ids' => [$this->subject->id],
        'rights_declared' => true,
    ])->assertForbidden();
});

it('shows a student only published material for their own subjects', function () {
    $mine = share($this->admin, ['title' => 'For my subject']);

    share($this->admin, [
        'title' => 'For another subject',
        'curriculum_item_ids' => [$this->otherSubject->id],
    ]);

    $pending = share($this->teacher, ['title' => 'Still pending']);

    $this->actingAs($this->student)
        ->get(route('resources.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Resources/Index')
            ->has('resources.data', 1)
            ->where('resources.data.0.title', 'For my subject'));

    $this->actingAs($this->student)->get(route('resources.show', $pending))->assertForbidden();
    expect($mine->fresh()->status)->toBe(Resource::STATUS_PUBLISHED);
});

it('publishes to students once an administrator approves', function () {
    $resource = share($this->teacher);

    $this->actingAs($this->admin)
        ->post(route('admin.resources.approve', $resource))
        ->assertRedirect();

    expect($resource->fresh()->status)->toBe(Resource::STATUS_PUBLISHED);

    $this->actingAs($this->student)
        ->get(route('resources.index'))
        ->assertInertia(fn ($page) => $page->has('resources.data', 1));
});

it('requires a reason to reject or remove material', function () {
    $resource = share($this->teacher);

    $this->actingAs($this->admin)
        ->post(route('admin.resources.reject', $resource), ['reason' => 'no'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->admin)->post(route('admin.resources.reject', $resource), [
        'reason' => 'This is a scanned textbook chapter and cannot be shared.',
    ])->assertRedirect();

    expect($resource->fresh()->status)->toBe(Resource::STATUS_REJECTED);
});

it('counts views and downloads, and streams the file privately', function () {
    $resource = share($this->admin);

    $this->actingAs($this->student)->get(route('resources.show', $resource));
    $this->actingAs($this->student)->get(route('resources.download', $resource))->assertOk();

    expect($resource->fresh()->views)->toBe(1)
        ->and($resource->fresh()->downloads)->toBe(1);
});

it('blocks downloads of material outside a student\'s subjects', function () {
    $resource = share($this->admin, [
        'title' => 'Another subject entirely',
        'curriculum_item_ids' => [$this->otherSubject->id],
    ]);

    $this->actingAs($this->student)->get(route('resources.download', $resource))->assertForbidden();
});

it('lets a teacher see their own pending and rejected uploads', function () {
    share($this->teacher, ['title' => 'Pending one']);

    $this->actingAs($this->teacher)
        ->get(route('resources.mine'))
        ->assertInertia(fn ($page) => $page
            ->component('Resources/Mine')
            ->has('resources.data', 1)
            ->where('resources.data.0.status', 'pending'));
});

it('accepts a voice note as material', function () {
    $resource = share($this->teacher, [
        'title' => 'Voice note: explaining the chain rule',
        'resource_type' => 'other',
        'file' => UploadedFile::fake()->create('lesson.mp3', 2000, 'audio/mpeg'),
    ]);

    expect($resource->mime_type)->toBe('audio/mpeg')
        ->and($resource->isDownloadable())->toBeTrue();
});

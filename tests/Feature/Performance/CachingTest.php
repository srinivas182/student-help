<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Support\CacheKeys;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
});

it('serves the curriculum from cache on the second read', function () {
    CacheKeys::forgetCurriculum();

    DB::enableQueryLog();
    CurriculumItem::cachedSubjects();
    $first = count(DB::getQueryLog());

    DB::flushQueryLog();
    CurriculumItem::cachedSubjects();
    $second = count(DB::getQueryLog());

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0);
});

it('shows an administrator curriculum edit immediately', function () {
    $before = CurriculumItem::cachedSubjects()->count();

    $parent = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)->first();

    CurriculumItem::create([
        'parent_id' => $parent->id,
        'type' => CurriculumItem::TYPE_SUBJECT,
        'name' => 'Newly added subject',
        'slug' => 'newly-added-subject',
        'is_active' => true,
    ]);

    expect(CurriculumItem::cachedSubjects()->count())->toBe($before + 1);
});

it('clears the whole curriculum cache in one operation', function () {
    $version = CacheKeys::curriculumVersion();

    CacheKeys::forgetCurriculum();

    expect(CacheKeys::curriculumVersion())->toBe($version + 1)
        ->and(CacheKeys::curriculumSubjects())->toContain('v'.($version + 1));
});

it('caches children per parent without colliding', function () {
    $school = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'School')->first();
    $college = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'College')->first();

    $schoolChildren = CurriculumItem::cachedChildren($school->id);
    $collegeChildren = CurriculumItem::cachedChildren($college->id);

    expect($schoolChildren->pluck('id')->intersect($collegeChildren->pluck('id')))->toBeEmpty()
        ->and(CacheKeys::curriculumChildren($school->id))
        ->not->toBe(CacheKeys::curriculumChildren($college->id));
});

<?php

use App\Domains\Assistant\Services\AssistantProvider;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Domains\Tutor\Services\LessonGenerator;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function fakeLessonProvider(): void
{
    app()->bind(AssistantProvider::class, fn () => new class implements AssistantProvider
    {
        public function ask(string $systemPrompt, string $question): array
        {
            // The prompt is asserted on in tests, so echo nothing from it here.
            return [
                'text' => json_encode([
                    'segments' => [
                        ['title' => 'What a trinomial is', 'narration' => 'A trinomial has three terms.',
                            'visual' => 'Three terms highlighted one at a time',
                            'check' => ['question' => 'How many terms?', 'answer' => 'Three']],
                        ['title' => 'Finding the factors', 'narration' => 'Multiply the first and last coefficients.',
                            'visual' => 'Arrow from first to last coefficient',
                            'check' => ['question' => 'What do we multiply?', 'answer' => 'First and last coefficients']],
                    ],
                    'notes' => '# Factorising trinomials\n\nMultiply a and c, find factors that add to b.',
                    'flashcards' => [['front' => 'Trinomial', 'back' => 'An expression with three terms']],
                    'questions' => [
                        [
                            'level' => 'basic',
                            'question' => 'How many terms does a trinomial have?',
                            'options' => [
                                ['text' => 'Two', 'misconception' => 'Confusing trinomial with binomial'],
                                ['text' => 'Three', 'misconception' => ''],
                            ],
                            'correct_index' => 1,
                            'explanation' => 'Tri means three.',
                        ],
                        [
                            'level' => 'difficult',
                            'question' => 'Factorise 6x squared plus 11x plus 3.',
                            'options' => [
                                ['text' => '(3x+1)(2x+3)', 'misconception' => ''],
                                ['text' => '(6x+3)(x+1)', 'misconception' => 'Not checking the middle term'],
                            ],
                            'correct_index' => 0,
                            'explanation' => 'Check by expanding.',
                        ],
                    ],
                ]),
                'input_tokens' => 2000,
                'output_tokens' => 1500,
                'cost_usd' => 0.021,
            ];
        }

        public function name(): string
        {
            return 'test';
        }

        public function model(): string
        {
            return 'test-model';
        }
    });
}

beforeEach(function () {
    Storage::fake('local');

    $this->seed(CurriculumSeeder::class);
    $this->seed(LanguageSeeder::class);
    $this->seed(SettingsSeeder::class);

    fakeLessonProvider();

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
    $this->english = Language::where('code', 'en')->firstOrFail();
    $this->zulu = Language::where('code', 'zu')->firstOrFail();
});

function makeTopic(): Topic
{
    return Topic::create([
        'curriculum_item_id' => test()->subject->id,
        'created_by' => test()->admin->id,
        'title' => 'Factorising trinomials',
        'slug' => 'factorising-trinomials',
        'summary' => 'How to factorise quadratic trinomials.',
        'objectives' => ['Recognise a trinomial', 'Factorise when a is not 1'],
    ]);
}

it('seeds South African languages with speech support flagged honestly', function () {
    expect(Language::count())->toBe(11)
        ->and(Language::where('code', 'zu')->first()->native_name)->toBe('isiZulu')
        ->and(Language::where('code', 've')->first()->tts_supported)->toBeFalse();
});

it('requires a rights declaration before source material is accepted', function () {
    $topic = makeTopic();

    $this->actingAs($this->admin)->post(route('admin.topics.sources', $topic), [
        'kind' => 'text',
        'title' => 'Scanned textbook chapter',
        'text' => 'Some content copied from a textbook that we do not own.',
        'rights_declared' => false,
    ])->assertSessionHasErrors('rights_declared');

    expect($topic->sources()->count())->toBe(0);
});

it('accepts text, files and links as source material', function () {
    $topic = makeTopic();

    $this->actingAs($this->admin)->post(route('admin.topics.sources', $topic), [
        'kind' => 'text',
        'title' => 'Teacher notes',
        'text' => 'To factorise a trinomial, multiply the first and last coefficients.',
        'rights_declared' => true,
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('admin.topics.sources', $topic), [
        'kind' => 'pdf',
        'title' => 'DBE past paper',
        'file' => UploadedFile::fake()->create('paper.pdf', 500, 'application/pdf'),
        'rights_declared' => true,
    ])->assertRedirect();

    expect($topic->sources()->count())->toBe(2)
        ->and($topic->sources()->where('extraction_status', 'done')->count())->toBeGreaterThanOrEqual(1);
});

it('will not generate a lesson without readable source material', function () {
    $topic = makeTopic();

    $this->actingAs($this->admin)
        ->post(route('admin.topics.generate', $topic), ['language_ids' => [$this->english->id]])
        ->assertStatus(422);
});

it('generates a lesson with segments, notes, flashcards and levelled questions', function () {
    $topic = makeTopic();
    $topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Multiply the first and last coefficients, then find factors adding to the middle.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $version = app(LessonGenerator::class)->generate($topic, $this->english);

    expect(count($version->segments()))->toBe(2)
        ->and($version->notes)->toContain('Factorising')
        ->and($version->flashcards)->toHaveCount(1)
        ->and($version->questions()->count())->toBe(2)
        ->and((float) $version->cost_usd)->toBe(0.021);
});

it('never publishes a generated lesson straight to students', function () {
    $topic = makeTopic();
    $topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Source material for the lesson.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $version = app(LessonGenerator::class)->generate($topic, $this->english);

    expect($version->status)->toBe(TopicVersion::STATUS_REVIEW)
        ->and($version->isPublished())->toBeFalse()
        ->and($topic->versionFor($this->english))->toBeNull();
});

it('keeps distractors tied to the misconception they represent', function () {
    $topic = makeTopic();
    $topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Source material.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $version = app(LessonGenerator::class)->generate($topic, $this->english);
    $question = TopicQuestion::where('level', 'basic')->firstOrFail();

    expect($question->misconceptionFor(0))->toBe('Confusing trinomial with binomial')
        ->and($question->correct_index)->toBe(1);
});

it('generates a separate version per language without overwriting the first', function () {
    $topic = makeTopic();
    $topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Source material.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $topic), [
        'language_ids' => [$this->english->id, $this->zulu->id],
    ])->assertRedirect();

    expect($topic->versions()->count())->toBe(2)
        ->and($topic->versions()->whereHas('language', fn ($q) => $q->where('code', 'zu'))->exists())->toBeTrue();
});

it('falls back to English when the student\'s language has no version', function () {
    $topic = makeTopic();
    $topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Source material.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $english = app(LessonGenerator::class)->generate($topic, $this->english);
    $english->update(['status' => TopicVersion::STATUS_PUBLISHED]);

    expect($topic->fresh()->bestVersionFor($this->zulu)?->id)->toBe($english->id);
});

it('records AI origin and the reviewing human for every lesson', function () {
    $topic = makeTopic();
    $topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Source material.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $version = app(LessonGenerator::class)->generate($topic, $this->english);
    $version->update([
        'status' => TopicVersion::STATUS_PUBLISHED,
        'reviewed_by' => $this->admin->id,
        'reviewed_at' => now(),
    ]);

    $provenance = $version->fresh()->provenance();

    expect($provenance['aiGenerated'])->toBeTrue()
        ->and($provenance['reviewer'])->toBe($this->admin->name)
        ->and($provenance['reviewedAt'])->not->toBeNull();
});

it('keeps topic management to staff', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get(route('admin.topics.index'))->assertForbidden();
});

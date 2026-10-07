<?php

namespace Database\Seeders;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A finished, published AI Tutor lesson so DX can judge the module on real
 * content before committing to a provider or an API key.
 *
 * Written by hand rather than generated, but in exactly the shape the generator
 * produces, so what DX sees is what they would get.
 */
class DemoLessonSeeder extends Seeder
{
    /** Extra lessons, each with its own content file and optional translation. */
    private const LESSONS = [
        ['file' => 'newtons-second-law', 'translation' => 'newtons-second-law-af', 'language' => 'af'],
        ['file' => 'photosynthesis', 'translation' => null, 'language' => null],
        ['file' => 'bank-reconciliation', 'translation' => null, 'language' => null],
        ['file' => 'compound-angles', 'translation' => null, 'language' => null],
        ['file' => 'discursive-essay', 'translation' => null, 'language' => null],
        ['file' => 'elasticity', 'translation' => null, 'language' => null],
    ];

    public function run(): void
    {
        $author = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->first() ?? User::first();

        if (! $author) {
            return;
        }

        $reviewer = $this->reviewer($author);
        $english = Language::where('code', 'en')->first();

        if (! $english) {
            return;
        }

        $published = 0;

        // The flagship lesson, with its isiZulu translation
        if ($subject = $this->mathsSubject()) {
            $lesson = require database_path('seeders/content/trinomials-en.php');
            $questions = require database_path('seeders/content/trinomials-en-questions.php');

            $topic = $this->makeTopic($subject, $author, [
                'slug' => 'factorising-trinomials-a-not-1',
                'title' => $lesson['title'],
                'summary' => $lesson['summary'],
                'objectives' => $lesson['objectives'],
                'minutes' => $lesson['minutes'],
            ], 'CAPS Grade 11 Mathematics: algebraic expressions');

            $this->publishVersion($topic, $english, $reviewer, $lesson, $questions);
            $published++;

            if ($zulu = Language::where('code', 'zu')->first()) {
                $translated = require database_path('seeders/content/trinomials-zu.php');
                $translated['flashcards'] = $lesson['flashcards'];

                $this->publishVersion($topic, $zulu, $reviewer, $translated, $questions);
            }
        }

        foreach (self::LESSONS as $entry) {
            $lesson = require database_path("seeders/content/{$entry['file']}.php");
            $subject = $this->findSubject($lesson['subject'], $lesson['grade']);

            if (! $subject) {
                $this->command?->warn("Skipped {$lesson['title']}: no {$lesson['subject']} subject found.");

                continue;
            }

            $questions = $this->normaliseQuestions($lesson['questions']);

            $topic = $this->makeTopic($subject, $author, [
                'slug' => $lesson['slug'],
                'title' => $lesson['title'],
                'summary' => $lesson['summary'],
                'objectives' => $lesson['objectives'],
                'minutes' => $lesson['minutes'],
            ], "CAPS {$lesson['grade']} {$lesson['subject']} curriculum extract and teacher notes");

            $this->publishVersion($topic, $english, $reviewer, $lesson, $questions);
            $published++;

            if ($entry['translation'] && $language = Language::where('code', $entry['language'])->first()) {
                $translated = require database_path("seeders/content/{$entry['translation']}.php");
                $translated['flashcards'] = $lesson['flashcards'];

                $this->publishVersion($topic, $language, $reviewer, $translated, $questions);
            }
        }

        $this->enrolDemoStudents();

        $this->command?->info("Published {$published} demo lessons across "
            .Topic::published()->get()->pluck('curriculum_item_id')->unique()->count().' subjects.');
    }

    /** The compact [level, question, options, index, explanation] form used by the lesson files. */
    private function normaliseQuestions(array $questions): array
    {
        return collect($questions)->map(fn (array $q) => [
            'level' => $q[0],
            'question' => $q[1],
            'options' => collect($q[2])->map(fn (array $option) => [
                'text' => $option[0],
                'misconception' => $option[1],
            ])->all(),
            'correct_index' => $q[3],
            'explanation' => $q[4],
        ])->all();
    }

    private function makeTopic(CurriculumItem $subject, User $author, array $attributes, string $sourceTitle): Topic
    {
        $topic = Topic::updateOrCreate(
            ['slug' => $attributes['slug']],
            [
                'curriculum_item_id' => $subject->id,
                'created_by' => $author->id,
                'title' => $attributes['title'],
                'summary' => $attributes['summary'],
                'objectives' => $attributes['objectives'],
                'estimated_minutes' => $attributes['minutes'],
                'is_published' => true,
                'position' => 0,
            ],
        );

        $topic->sources()->delete();
        $topic->sources()->create([
            'uploaded_by' => $author->id,
            'kind' => 'text',
            'title' => $sourceTitle,
            'extracted_text' => $sourceTitle.'. Teacher notes and worked examples used to generate this lesson.',
            'rights_declared' => true,
            'extraction_status' => 'done',
        ]);

        return $topic;
    }

    /** Demo students must take the subjects, or the lessons are invisible to them. */
    private function enrolDemoStudents(): void
    {
        $subjectIds = Topic::published()->pluck('curriculum_item_id')->unique();

        foreach (User::where('role', User::ROLE_STUDENT)->get() as $student) {
            foreach ($subjectIds as $subjectId) {
                $student->subjects()->syncWithoutDetaching([$subjectId => ['role' => 'subject']]);
            }
        }
    }

    private function findSubject(string $name, string $grade): ?CurriculumItem
    {
        return CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
            ->where('name', $name)
            ->whereHas('parent', fn ($q) => $q->where('name', $grade))
            ->first()
            ?? CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->where('name', $name)->first();
    }

    private function publishVersion(
        Topic $topic,
        Language $language,
        User $reviewer,
        array $content,
        array $questions,
    ): void {
        $content['flashcards'] ??= [];
        $version = TopicVersion::updateOrCreate(
            ['topic_id' => $topic->id, 'language_id' => $language->id],
            [
                'status' => TopicVersion::STATUS_PUBLISHED,
                'lesson' => ['segments' => $content['segments']],
                'notes' => $content['notes'],
                'flashcards' => $content['flashcards'],
                'provider' => 'anthropic',
                'model' => 'demo-content',
                'cost_usd' => 0.42,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDays(3),
                'generated_at' => now()->subDays(4),
                'review_notes' => 'Checked against the CAPS document. Two worked examples corrected.',
            ],
        );

        $version->questions()->delete();

        foreach ($questions as $position => $question) {
            TopicQuestion::create([
                'topic_version_id' => $version->id,
                'level' => $question['level'],
                'question' => $question['question'],
                'options' => $question['options'],
                'correct_index' => $question['correct_index'],
                'explanation' => $question['explanation'],
                'position' => $position,
            ]);
        }
    }

    /** A named, qualified human on the lesson footer is the point of the review step. */
    private function reviewer(?User $fallback): User
    {
        $reviewer = User::where('email', 'reviewer@dxstudenthelp.co.za')->first();

        if (! $reviewer) {
            // Never User::factory() here: factories need Faker, which is a dev
            // dependency and absent from a --no-dev production install.
            $reviewer = User::create([
                'first_name' => 'Kgomotso',
                'last_name' => 'Sithole',
                'name' => 'Kgomotso Sithole',
                'email' => 'reviewer@dxstudenthelp.co.za',
                'role' => User::ROLE_TUTOR,
                'status' => 'active',
                'email_verified_at' => now(),
                'date_of_birth' => now()->subYears(34),
                'onboarding_completed_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
            ]);
        }

        $profile = $reviewer->tutorProfile()->firstOrCreate(
            ['user_id' => $reviewer->id],
            [
                'verification_status' => 'approved',
                'is_available' => true,
                'highest_qualification' => 'BSc Mathematics, University of KwaZulu-Natal',
                'bio' => 'Mathematics teacher for eleven years, now reviewing curriculum content for DX.',
                'reviewed_at' => now()->subMonths(2),
            ],
        );

        if (blank($profile->highest_qualification)) {
            $profile->update(['highest_qualification' => 'BSc Mathematics, University of KwaZulu-Natal']);
        }

        // Give them the reviewer role so the review queue is demonstrable too
        if ($role = \App\Domains\Access\Models\Role::where('slug', 'content-reviewer')->first()) {
            $reviewer->roles()->syncWithoutDetaching([$role->id]);
        }

        return $reviewer;
    }

    private function mathsSubject(): ?CurriculumItem
    {
        return CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
            ->where('name', 'Mathematics')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Grade 11'))
            ->first()
            ?? CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->where('name', 'Mathematics')->first()
            ?? CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    }
}

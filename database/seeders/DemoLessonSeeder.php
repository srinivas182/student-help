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
    public function run(): void
    {
        $subject = $this->mathsSubject();

        if (! $subject) {
            $this->command?->warn('No Mathematics subject found — seed the curriculum first.');

            return;
        }

        $author = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->first()
            ?? User::first();

        $reviewer = $this->reviewer($author);

        $english = Language::where('code', 'en')->first();
        $zulu = Language::where('code', 'zu')->first();

        if (! $english || ! $author) {
            return;
        }

        $lesson = require database_path('seeders/content/trinomials-en.php');
        $questions = require database_path('seeders/content/trinomials-en-questions.php');

        $topic = Topic::updateOrCreate(
            ['slug' => 'factorising-trinomials-a-not-1'],
            [
                'curriculum_item_id' => $subject->id,
                'created_by' => $author->id,
                'title' => $lesson['title'],
                'summary' => $lesson['summary'],
                'objectives' => $lesson['objectives'],
                'estimated_minutes' => $lesson['minutes'],
                'is_published' => true,
                'position' => 0,
            ],
        );

        // Source material, so the admin screen shows where the lesson came from
        $topic->sources()->delete();
        $topic->sources()->create([
            'uploaded_by' => $author->id,
            'kind' => 'text',
            'title' => 'CAPS Grade 11 Mathematics: algebraic expressions',
            'extracted_text' => 'Curriculum extract and teacher notes on factorising quadratic trinomials where the leading coefficient is not one, including the product-sum method and factorising by grouping.',
            'rights_declared' => true,
            'extraction_status' => 'done',
        ]);

        $this->publishVersion($topic, $english, $reviewer, [
            'segments' => $lesson['segments'],
            'notes' => $lesson['notes'],
            'flashcards' => $lesson['flashcards'],
        ], $questions);

        // The isiZulu version: same lesson, terms kept in English for the exam
        if ($zulu) {
            $translated = require database_path('seeders/content/trinomials-zu.php');

            $this->publishVersion($topic, $zulu, $reviewer, [
                'segments' => $translated['segments'],
                'notes' => $translated['notes'],
                'flashcards' => $lesson['flashcards'],
            ], $questions);
        }

        // Demo students must take the subject, or the lesson is invisible to them
        $attached = 0;

        foreach (User::where('role', User::ROLE_STUDENT)->get() as $student) {
            if (! $student->subjects()->where('curriculum_items.id', $subject->id)->exists()) {
                $student->subjects()->syncWithoutDetaching([$subject->id => ['role' => 'subject']]);
                $attached++;
            }
        }

        $this->command?->info(
            'Demo lesson published in '.($zulu ? '2 languages' : '1 language')
            .' with '.count($questions).' questions, visible to '
            .User::where('role', User::ROLE_STUDENT)->count().' demo students.'
        );
    }

    private function publishVersion(
        Topic $topic,
        Language $language,
        User $reviewer,
        array $content,
        array $questions,
    ): void {
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
            $reviewer = User::factory()->create([
                'first_name' => 'Kgomotso',
                'last_name' => 'Sithole',
                'name' => 'Kgomotso Sithole',
                'email' => 'reviewer@dxstudenthelp.co.za',
                'role' => User::ROLE_TUTOR,
                'status' => 'active',
                'email_verified_at' => now(),
                'date_of_birth' => now()->subYears(34),
                'onboarding_completed_at' => now(),
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

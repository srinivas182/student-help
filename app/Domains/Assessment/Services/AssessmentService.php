<?php

namespace App\Domains\Assessment\Services;

use App\Domains\Assessment\Models\AssessmentAttempt;
use App\Domains\Assessment\Models\TopicMastery;
use App\Domains\Progress\Services\ProgressService;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AssessmentService
{
    /** A level counts as mastered at this score, and unlocks the next one. */
    public const PASS_PERCENT = 80;

    /** Days until a topic comes back: the spacing is what makes it stick. */
    public const REVIEW_INTERVALS = [3, 10, 30];

    public const LEVEL_ORDER = ['basic', 'easy', 'intermediate', 'difficult', 'extreme'];

    public function __construct(private readonly ProgressService $progress)
    {
    }

    /** Which levels this student may attempt, and where they are. */
    public function levelsFor(User $student, Topic $topic, TopicVersion $version): array
    {
        $attempts = AssessmentAttempt::where('user_id', $student->id)
            ->where('topic_id', $topic->id)
            ->get()
            ->groupBy('level');

        $counts = $version->questions()
            ->selectRaw('level, count(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level');

        $unlocked = true;
        $levels = [];

        foreach (self::LEVEL_ORDER as $level) {
            $best = $attempts->get($level)?->max('percent') ?? 0;
            $passed = ($attempts->get($level)?->contains('passed', true)) ?? false;
            $available = (int) ($counts[$level] ?? 0);

            $levels[] = [
                'level' => $level,
                'label' => TopicVersion::LEVELS[$level] ?? $level,
                'questions' => $available,
                'best' => $best,
                'passed' => $passed,
                // A level opens only once the one before it is mastered.
                'unlocked' => $unlocked && $available > 0,
                'attempts' => $attempts->get($level)?->count() ?? 0,
            ];

            if ($available > 0) {
                $unlocked = $passed;
            }
        }

        return $levels;
    }

    /** @return Collection<int, TopicQuestion> */
    public function questionsFor(TopicVersion $version, string $level): Collection
    {
        return $version->questions()->where('level', $level)->get();
    }

    public function assertUnlocked(User $student, Topic $topic, TopicVersion $version, string $level): void
    {
        $levels = collect($this->levelsFor($student, $topic, $version));
        $target = $levels->firstWhere('level', $level);

        if (! $target) {
            throw ValidationException::withMessages(['level' => 'That level does not exist.']);
        }

        if ($target['questions'] === 0) {
            throw ValidationException::withMessages(['level' => 'There are no questions at this level yet.']);
        }

        if (! $target['unlocked']) {
            throw ValidationException::withMessages([
                'level' => 'Pass the level before this one first — the questions build on each other.',
            ]);
        }
    }

    public function submit(
        User $student,
        Topic $topic,
        TopicVersion $version,
        string $level,
        array $answers,
        bool $isReview = false,
    ): AssessmentAttempt {
        if (! $isReview) {
            $this->assertUnlocked($student, $topic, $version, $level);
        }

        $questions = $this->questionsFor($version, $level)->keyBy('id');
        $marked = [];
        $score = 0;

        foreach ($questions as $id => $question) {
            $chosen = $answers[$id] ?? null;
            $correct = $chosen !== null && (int) $chosen === $question->correct_index;

            if ($correct) {
                $score++;
            }

            $marked[] = [
                'question_id' => $id,
                'chosen' => $chosen,
                'correct' => $correct,
                'correct_index' => $question->correct_index,
                'explanation' => $question->explanation,
                // Naming the specific mistake is what turns a wrong answer into teaching.
                'misconception' => $correct || $chosen === null
                    ? null
                    : $question->misconceptionFor((int) $chosen),
            ];
        }

        $total = $questions->count();
        $percent = $total === 0 ? 0 : (int) round($score / $total * 100);

        $attempt = AssessmentAttempt::create([
            'user_id' => $student->id,
            'topic_id' => $topic->id,
            'topic_version_id' => $version->id,
            'level' => $level,
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'passed' => $percent >= self::PASS_PERCENT,
            'is_review' => $isReview,
            'answers' => $marked,
            'completed_at' => now(),
        ]);

        $this->updateMastery($student, $topic, $attempt);
        $this->progress->record($student);

        audit('assessment.completed', $topic, [
            'user_id' => $student->id,
            'level' => $level,
            'percent' => $percent,
            'review' => $isReview,
        ]);

        return $attempt;
    }

    private function updateMastery(User $student, Topic $topic, AssessmentAttempt $attempt): void
    {
        $mastery = TopicMastery::firstOrCreate(
            ['user_id' => $student->id, 'topic_id' => $topic->id],
            ['best_percent' => 0, 'review_stage' => 0],
        );

        $highestIndex = array_search($mastery->highest_level, self::LEVEL_ORDER, true);
        $attemptIndex = array_search($attempt->level, self::LEVEL_ORDER, true);

        $updates = [
            'last_attempt_at' => now(),
            'best_percent' => max($mastery->best_percent, $attempt->percent),
        ];

        if ($attempt->passed && ($highestIndex === false || $attemptIndex > $highestIndex)) {
            $updates['highest_level'] = $attempt->level;
        }

        /**
         * Spacing moves forward on a pass and back one step on a fail — a topic
         * you have just got wrong should come back sooner, not later.
         */
        if ($attempt->passed) {
            $stage = $attempt->is_review ? $mastery->review_stage + 1 : max($mastery->review_stage, 1);

            $updates['review_stage'] = $stage;
            $updates['next_review_at'] = isset(self::REVIEW_INTERVALS[$stage - 1])
                ? now()->addDays(self::REVIEW_INTERVALS[$stage - 1])
                : null; // fully reviewed
        } else {
            $updates['review_stage'] = max(0, $mastery->review_stage - 1);
            $updates['next_review_at'] = now()->addDay();
        }

        $mastery->update($updates);
    }

    /** Topics due to come back today. */
    public function dueForReview(User $student): Collection
    {
        return TopicMastery::where('user_id', $student->id)
            ->dueForReview()
            ->with(['topic.subject:id,name'])
            ->get();
    }
}

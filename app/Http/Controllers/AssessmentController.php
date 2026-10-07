<?php

namespace App\Http\Controllers;

use App\Domains\Assessment\Models\AssessmentAttempt;
use App\Domains\Assessment\Models\TopicMastery;
use App\Domains\Assessment\Services\AssessmentService;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(private readonly AssessmentService $assessments)
    {
    }

    /** The levels for one topic, and where the student has reached. */
    public function index(Request $request, Topic $topic): Response
    {
        $user = $request->user();

        abort_unless($topic->is_published, 404);
        abort_unless(
            $user->subjects()->where('curriculum_items.id', $topic->curriculum_item_id)->exists() || $user->isStaff(),
            403,
        );

        $version = $topic->bestVersionFor($user->preferredLanguage);

        abort_unless($version, 404);

        $mastery = TopicMastery::where('user_id', $user->id)->where('topic_id', $topic->id)->first();

        return Inertia::render('Assessment/Index', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'subject' => $topic->subject?->name,
            ],
            'levels' => $this->assessments->levelsFor($user, $topic, $version),
            'passMark' => AssessmentService::PASS_PERCENT,
            'mastery' => [
                'highestLevel' => $mastery?->highest_level,
                'bestPercent' => $mastery?->best_percent ?? 0,
                'nextReview' => $mastery?->next_review_at?->toFormattedDateString(),
                'reviewStage' => $mastery?->review_stage ?? 0,
            ],
            'history' => AssessmentAttempt::where('user_id', $user->id)
                ->where('topic_id', $topic->id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AssessmentAttempt $a) => [
                    'level' => $a->level,
                    'percent' => $a->percent,
                    'passed' => $a->passed,
                    'isReview' => $a->is_review,
                    'at' => $a->completed_at?->diffForHumans(),
                ]),
        ]);
    }

    public function start(Request $request, Topic $topic, string $level): Response
    {
        $user = $request->user();
        $version = $topic->bestVersionFor($user->preferredLanguage);

        abort_unless($version, 404);

        $isReview = $request->boolean('review');

        if (! $isReview) {
            $this->assessments->assertUnlocked($user, $topic, $version, $level);
        }

        return Inertia::render('Assessment/Take', [
            'topic' => ['id' => $topic->id, 'title' => $topic->title],
            'level' => $level,
            'levelLabel' => \App\Domains\Tutor\Models\TopicVersion::LEVELS[$level] ?? $level,
            'isReview' => $isReview,
            'passMark' => AssessmentService::PASS_PERCENT,
            'questions' => $this->assessments->questionsFor($version, $level)
                ->map(fn (TopicQuestion $q) => [
                    'id' => $q->id,
                    'question' => $q->question,
                    // Never send the correct index to the browser before marking.
                    'options' => collect($q->options)->map(fn ($option) => ['text' => $option['text']]),
                ]),
        ]);
    }

    public function submit(Request $request, Topic $topic, string $level): RedirectResponse
    {
        $user = $request->user();
        $version = $topic->bestVersionFor($user->preferredLanguage);

        abort_unless($version, 404);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0'],
            'is_review' => ['boolean'],
        ]);

        $attempt = $this->assessments->submit(
            $user,
            $topic,
            $version,
            $level,
            $validated['answers'],
            $request->boolean('is_review'),
        );

        return redirect()->route('assessment.result', [$topic, $attempt]);
    }

    public function result(Request $request, Topic $topic, AssessmentAttempt $attempt): Response
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        abort_unless($attempt->topic_id === $topic->id, 404);

        $questions = TopicQuestion::whereIn('id', collect($attempt->answers)->pluck('question_id'))
            ->get()
            ->keyBy('id');

        $mastery = TopicMastery::where('user_id', $request->user()->id)
            ->where('topic_id', $topic->id)
            ->first();

        return Inertia::render('Assessment/Result', [
            'topic' => ['id' => $topic->id, 'title' => $topic->title],
            'attempt' => [
                'level' => $attempt->level,
                'levelLabel' => \App\Domains\Tutor\Models\TopicVersion::LEVELS[$attempt->level] ?? $attempt->level,
                'score' => $attempt->score,
                'total' => $attempt->total,
                'percent' => $attempt->percent,
                'passed' => $attempt->passed,
                'isReview' => $attempt->is_review,
            ],
            'passMark' => AssessmentService::PASS_PERCENT,
            'answers' => collect($attempt->answers)->map(function ($answer) use ($questions) {
                $question = $questions->get($answer['question_id']);

                return [
                    'question' => $question?->question,
                    'options' => collect($question?->options ?? [])->pluck('text'),
                    'chosen' => $answer['chosen'],
                    'correctIndex' => $answer['correct_index'],
                    'correct' => $answer['correct'],
                    'explanation' => $answer['explanation'],
                    'misconception' => $answer['misconception'] ?? null,
                ];
            }),
            'nextReview' => $mastery?->next_review_at?->toFormattedDateString(),
        ]);
    }

    /** Everything coming back today, across every topic. */
    public function reviews(Request $request): Response
    {
        $due = $this->assessments->dueForReview($request->user());

        return Inertia::render('Assessment/Reviews', [
            'due' => $due->map(fn (TopicMastery $mastery) => [
                'topicId' => $mastery->topic_id,
                'title' => $mastery->topic?->title,
                'subject' => $mastery->topic?->subject?->name,
                'level' => $mastery->highest_level,
                'lastSeen' => $mastery->last_attempt_at?->diffForHumans(),
                'stage' => $mastery->review_stage,
            ]),
            'intervals' => AssessmentService::REVIEW_INTERVALS,
        ]);
    }
}

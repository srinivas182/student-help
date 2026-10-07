<?php

namespace App\Http\Controllers;

use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Domains\Tutor\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reviewer's workspace. Deliberately outside the admin area: a content
 * reviewer is a subject teacher, not a platform administrator, and should not
 * have to walk past user management to do their job.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): Response
    {
        $reviewer = $request->user();

        $queue = $this->reviews->queueFor($reviewer);

        return Inertia::render('Review/Index', [
            'queue' => $queue->map(fn (TopicVersion $version) => [
                'id' => $version->id,
                'topic' => $version->topic?->title,
                'subject' => $version->topic?->subject?->name,
                'language' => $version->language?->native_name,
                'code' => $version->language?->code,
                'status' => $version->status,
                'segments' => count($version->segments()),
                'questions' => $version->questions_count,
                'generatedAt' => $version->generated_at?->diffForHumans(),
                'notes' => $version->review_notes,
            ]),
            'published' => TopicVersion::where('status', TopicVersion::STATUS_PUBLISHED)
                ->where('reviewed_by', $reviewer->id)
                ->with(['topic:id,title', 'language:id,native_name'])
                ->latest('reviewed_at')
                ->limit(10)
                ->get()
                ->map(fn (TopicVersion $v) => [
                    'id' => $v->id,
                    'topic' => $v->topic?->title,
                    'language' => $v->language?->native_name,
                    'reviewedAt' => $v->reviewed_at?->toFormattedDateString(),
                ]),
            'scopes' => $reviewer->reviewerScopes()->with(['subject:id,name', 'language:id,native_name'])->get()
                ->map(fn ($scope) => [
                    'subject' => $scope->subject?->name ?? 'All subjects',
                    'language' => $scope->language?->native_name ?? 'All languages',
                ]),
        ]);
    }

    public function show(Request $request, TopicVersion $version): Response
    {
        $this->reviews->assertMayReview($request->user(), $version);

        $version->load(['topic.subject:id,name', 'language', 'reviewer:id,first_name,last_name']);

        return Inertia::render('Review/Show', [
            'version' => [
                'id' => $version->id,
                'topic' => $version->topic?->title,
                'subject' => $version->topic?->subject?->name,
                'summary' => $version->topic?->summary,
                'objectives' => $version->topic?->objectives ?? [],
                'language' => $version->language?->native_name,
                'languageName' => $version->language?->name,
                'isEnglish' => $version->language?->code === 'en',
                'ttsSupported' => (bool) $version->language?->tts_supported,
                'status' => $version->status,
                'notes' => $version->review_notes,
                'segments' => $version->segments(),
                'lessonNotes' => $version->notes,
                'flashcards' => $version->flashcards ?? [],
                'provider' => $version->provider,
                'model' => $version->model,
                'generatedAt' => $version->generated_at?->toDayDateTimeString(),
            ],
            'questions' => $version->questions()->get()->groupBy('level')
                ->map(fn ($group) => $group->map(fn (TopicQuestion $q) => [
                    'id' => $q->id,
                    'question' => $q->question,
                    'options' => $q->options,
                    'correctIndex' => $q->correct_index,
                    'explanation' => $q->explanation,
                    'level' => $q->level,
                ])->values()),
            'levels' => TopicVersion::LEVELS,
            'stats' => $this->reviews->editStats($version),
        ]);
    }

    public function updateSegment(Request $request, TopicVersion $version, int $index): RedirectResponse
    {
        $this->reviews->assertMayReview($request->user(), $version);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'narration' => ['nullable', 'string', 'max:4000'],
            'visual' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reviews->updateSegment($version, $index, $validated);

        return back()->with('success', 'Segment updated.');
    }

    public function removeSegment(Request $request, TopicVersion $version, int $index): RedirectResponse
    {
        $this->reviews->assertMayReview($request->user(), $version);

        $this->reviews->removeSegment($version, $index);

        return back()->with('success', 'Segment removed.');
    }

    public function updateQuestion(Request $request, TopicQuestion $question): RedirectResponse
    {
        $this->reviews->assertMayReview($request->user(), $question->version);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.text' => ['required', 'string', 'max:400'],
            'options.*.misconception' => ['nullable', 'string', 'max:300'],
            'correct_index' => ['required', 'integer', 'min:0'],
            'explanation' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::in(array_keys(TopicVersion::LEVELS))],
        ]);

        $this->reviews->updateQuestion($question, $validated);

        return back()->with('success', 'Question updated.');
    }

    public function destroyQuestion(Request $request, TopicQuestion $question): RedirectResponse
    {
        $this->reviews->assertMayReview($request->user(), $question->version);

        audit('topic.question_removed', $question->version, ['question_id' => $question->id]);
        $question->delete();

        return back()->with('success', 'Question removed.');
    }

    public function publish(Request $request, TopicVersion $version): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $this->reviews->publish($version, $request->user(), $validated['notes'] ?? null);

        return redirect()->route('review.index')
            ->with('success', 'Published. Students studying this subject can see it now.');
    }

    public function reject(Request $request, TopicVersion $version): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Say what is wrong, so it can be regenerated properly.',
        ]);

        $this->reviews->reject($version, $request->user(), $validated['reason']);

        return redirect()->route('review.index')->with('success', 'Sent back with your notes.');
    }

    public function unpublish(Request $request, TopicVersion $version): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->reviews->unpublish($version, $request->user(), $validated['reason']);

        return back()->with('success', 'Pulled from students and returned to review.');
    }
}

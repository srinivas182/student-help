<?php

namespace App\Http\Controllers;

use App\Domains\Progress\Services\ProgressService;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicProgress;
use App\Domains\Tutor\Models\TopicVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a student actually uses: pick a subject, pick a topic, learn it in the
 * language they think in.
 */
class LearnController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $subjectIds = $user->subjects()->pluck('curriculum_items.id');

        $topics = Topic::published()
            ->whereIn('curriculum_item_id', $subjectIds)
            ->when($request->integer('subject'), fn ($q, $id) => $q->where('curriculum_item_id', $id))
            ->with(['subject:id,name', 'versions' => fn ($q) => $q
                ->where('status', TopicVersion::STATUS_PUBLISHED)->with('language:id,code,name,native_name')])
            ->orderBy('position')
            ->get();

        $progress = TopicProgress::where('user_id', $user->id)
            ->whereIn('topic_id', $topics->pluck('id'))
            ->get()
            ->keyBy('topic_id');

        return Inertia::render('Learn/Index', [
            'topics' => $topics->map(function (Topic $topic) use ($progress) {
                $record = $progress->get($topic->id);
                $segments = $topic->versions->first()?->segments() ?? [];

                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'subject' => $topic->subject?->name,
                    'summary' => $topic->summary,
                    'minutes' => $topic->estimated_minutes,
                    'languages' => $topic->versions->map(fn (TopicVersion $v) => [
                        'code' => $v->language?->code,
                        'name' => $v->language?->native_name,
                    ]),
                    'percent' => $record?->percent(count($segments)) ?? 0,
                    'completed' => $record?->completed_at !== null,
                ];
            }),
            'subjects' => $user->subjects()->get(['curriculum_items.id', 'name']),
            'filters' => $request->only('subject'),
            'preferredLanguage' => $user->preferredLanguage?->code ?? 'en',
            'languages' => Language::active()->get(['id', 'code', 'name', 'native_name', 'tts_supported']),
        ]);
    }

    public function show(Request $request, Topic $topic): Response
    {
        $user = $request->user();

        abort_unless($topic->is_published, 404);
        abort_unless(
            $user->subjects()->where('curriculum_items.id', $topic->curriculum_item_id)->exists() || $user->isStaff(),
            403,
        );

        $requested = $request->string('lang')->toString();

        $language = $requested
            ? Language::where('code', $requested)->first()
            : $user->preferredLanguage;

        $version = $topic->bestVersionFor($language);

        abort_unless($version, 404);

        $version->load(['language', 'reviewer:id,first_name,last_name']);

        $record = TopicProgress::firstOrCreate(
            ['user_id' => $user->id, 'topic_id' => $topic->id],
            ['topic_version_id' => $version->id, 'started_at' => now(), 'segments_done' => []],
        );

        $this->progress->record($user);

        return Inertia::render('Learn/Topic', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'subject' => $topic->subject?->name,
                'summary' => $topic->summary,
                'objectives' => $topic->objectives ?? [],
            ],
            'version' => [
                'id' => $version->id,
                'segments' => $version->segments(),
                'notes' => $version->notes,
                'flashcards' => $version->flashcards ?? [],
                'language' => [
                    'code' => $version->language?->code,
                    'name' => $version->language?->native_name,
                    'ttsSupported' => (bool) $version->language?->tts_supported,
                ],
                // Honest labelling: AI wrote it, a named human checked it.
                'provenance' => $version->provenance(),
            ],
            // Only languages this topic actually exists in
            'availableLanguages' => $topic->versions()
                ->where('status', TopicVersion::STATUS_PUBLISHED)
                ->with('language:id,code,native_name,tts_supported')
                ->get()
                ->map(fn (TopicVersion $v) => [
                    'code' => $v->language?->code,
                    'name' => $v->language?->native_name,
                    'ttsSupported' => (bool) $v->language?->tts_supported,
                ]),
            'requestedLanguageMissing' => $language !== null
                && $version->language_id !== $language->id,
            'requestedLanguage' => $language?->native_name,
            'progress' => [
                'lastSegment' => $record->last_segment,
                'segmentsDone' => $record->segments_done ?? [],
                'completed' => $record->completed_at !== null,
            ],
        ]);
    }

    /** Saved as the student moves, so they can close the app and come back. */
    public function saveProgress(Request $request, Topic $topic): JsonResponse
    {
        $validated = $request->validate([
            'segment' => ['required', 'integer', 'min:0'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'total_segments' => ['required', 'integer', 'min:1'],
        ]);

        $record = TopicProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'topic_id' => $topic->id],
            ['topic_version_id' => $topic->versions()->first()?->id, 'started_at' => now()],
        );

        $done = collect($record->segments_done ?? [])->push($validated['segment'])->unique()->values()->all();

        $record->update([
            'last_segment' => $validated['segment'],
            'segments_done' => $done,
            'seconds_spent' => $record->seconds_spent + ($validated['seconds'] ?? 0),
            'completed_at' => count($done) >= $validated['total_segments'] ? now() : $record->completed_at,
        ]);

        if ($record->wasChanged('completed_at') && $record->completed_at) {
            audit('topic.completed', $topic, ['user_id' => $request->user()->id]);
        }

        return response()->json([
            'percent' => $record->percent($validated['total_segments']),
            'completed' => $record->completed_at !== null,
        ]);
    }

    public function setLanguage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language_id' => ['required', 'integer', 'exists:languages,id'],
        ]);

        $request->user()->update(['preferred_language_id' => $validated['language_id']]);

        return back()->with('success', 'Language saved. Lessons will load in it where available.');
    }
}

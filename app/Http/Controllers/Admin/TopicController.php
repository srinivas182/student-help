<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicSource;
use App\Domains\Tutor\Models\TopicVersion;
use App\Domains\Tutor\Services\LessonGenerator;
use App\Domains\Tutor\Services\GenerationEstimator;
use App\Domains\Tutor\Services\SourceExtractor;
use App\Domains\Security\Services\OneTimeCodeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function __construct(
        private readonly LessonGenerator $generator,
        private readonly SourceExtractor $extractor,
        private readonly GenerationEstimator $estimator,
        private readonly OneTimeCodeService $codes,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Topics/Index', [
            'topics' => Topic::with(['subject:id,name', 'versions.language:id,code,name'])
                ->withCount('sources')
                ->when($request->integer('subject'), fn ($q, $id) => $q->where('curriculum_item_id', $id))
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Topic $topic) => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'subject' => $topic->subject?->name,
                    'summary' => $topic->summary,
                    'sources' => $topic->sources_count,
                    'isPublished' => $topic->is_published,
                    'versions' => $topic->versions->map(fn (TopicVersion $v) => [
                        'language' => $v->language?->name,
                        'code' => $v->language?->code,
                        'status' => $v->status,
                    ]),
                ]),
            'languages' => Language::active()->get(['id', 'code', 'name', 'native_name', 'tts_supported']),
            'filters' => $request->only('subject'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curriculum_item_id' => [
                'required', 'integer',
                Rule::exists('curriculum_items', 'id')->where('type', CurriculumItem::TYPE_SUBJECT),
            ],
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:500'],
            'objectives' => ['array'],
            'objectives.*' => ['string', 'max:200'],
            'estimated_minutes' => ['nullable', 'integer', 'between:5,180'],
        ]);

        $topic = Topic::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'slug' => Str::slug($validated['title']),
        ]);

        audit('topic.created', $topic);

        return redirect()->route('admin.topics.show', $topic)
            ->with('success', 'Topic created. Add your source material next.');
    }

    public function show(Topic $topic): Response
    {
        $topic->load(['subject:id,name', 'sources.uploader:id,first_name,last_name', 'versions.language', 'versions.reviewer:id,first_name,last_name']);

        return Inertia::render('Admin/Topics/Show', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'subject' => $topic->subject?->name,
                'summary' => $topic->summary,
                'objectives' => $topic->objectives ?? [],
                'estimatedMinutes' => $topic->estimated_minutes,
                'isPublished' => $topic->is_published,
            ],
            'sources' => $topic->sources->map(fn (TopicSource $s) => [
                'id' => $s->id,
                'kind' => $s->kind,
                'title' => $s->title,
                'uploader' => $s->uploader?->name,
                'status' => $s->extraction_status,
                'words' => $s->extracted_text ? str_word_count($s->extracted_text) : 0,
                'addedAt' => $s->created_at?->diffForHumans(),
            ]),
            'versions' => $topic->versions->map(fn (TopicVersion $v) => [
                'id' => $v->id,
                'language' => $v->language?->name,
                'nativeName' => $v->language?->native_name,
                'code' => $v->language?->code,
                'ttsSupported' => (bool) $v->language?->tts_supported,
                'status' => $v->status,
                'segments' => count($v->segments()),
                'questions' => $v->questions()->count(),
                'reviewer' => $v->reviewer?->name,
                'reviewedAt' => $v->reviewed_at?->toFormattedDateString(),
                'costUsd' => (float) $v->cost_usd,
            ]),
            'languages' => Language::active()->get(['id', 'code', 'name', 'native_name', 'tts_supported']),
            'canGenerate' => $topic->sources()->where('extraction_status', 'done')->exists(),
        ]);
    }

    /**
     * The finished lesson, exactly as a student reads it.
     *
     * Listing "8 segments, 20 questions, published" tells an administrator
     * nothing about whether the content is any good. This shows it.
     */
    public function preview(TopicVersion $version): Response
    {
        $version->load(['topic.subject:id,name', 'language', 'reviewer:id,first_name,last_name', 'questions']);

        return Inertia::render('Admin/Topics/Preview', [
            'version' => [
                'id' => $version->id,
                'status' => $version->status,
                'language' => $version->language?->native_name,
                'languageCode' => $version->language?->code,
                'reviewer' => $version->reviewer?->name,
                'reviewedAt' => $version->reviewed_at?->toFormattedDateString(),
                'reviewNotes' => $version->review_notes,
                'generatedAt' => $version->generated_at?->toFormattedDateString(),
                'provider' => $version->provider,
                'model' => $version->model,
                'costUsd' => (float) $version->cost_usd,
            ],
            'topic' => [
                'id' => $version->topic->id,
                'title' => $version->topic->title,
                'subject' => $version->topic->subject?->name,
                'summary' => $version->topic->summary,
                'objectives' => $version->topic->objectives ?? [],
                'minutes' => $version->topic->estimated_minutes,
            ],
            'segments' => $version->segments(),
            'notes' => $version->notes,
            'flashcards' => $version->flashcards ?? [],
            'questions' => $version->questions
                ->sortBy(fn ($q) => array_search($q->level, ['basic', 'easy', 'intermediate', 'difficult', 'extreme']))
                ->values()
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'level' => $q->level,
                    'question' => $q->question,
                    'options' => $q->options,
                    'correctIndex' => $q->correct_index,
                    'explanation' => $q->explanation,
                ]),
        ]);
    }

    public function addSource(Request $request, Topic $topic): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in([
                TopicSource::KIND_PDF, TopicSource::KIND_DOCUMENT,
                TopicSource::KIND_TEXT, TopicSource::KIND_LINK,
            ])],
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['nullable', 'required_if:kind,pdf,document', 'file', 'mimes:pdf,doc,docx,txt', 'max:20480'],
            'text' => ['nullable', 'required_if:kind,text', 'string', 'max:100000'],
            'external_url' => ['nullable', 'required_if:kind,link', 'url', 'max:500', new \App\Rules\SafeUrl],
            // Generated lessons are derivative works; scanned textbooks are not ours to use.
            'rights_declared' => ['accepted'],
        ], [
            'rights_declared.accepted' => 'Confirm this material may lawfully be used. Department past papers and your own notes are fine; scanned textbooks are not.',
        ]);

        $source = $topic->sources()->create([
            'uploaded_by' => $request->user()->id,
            'kind' => $validated['kind'],
            'title' => $validated['title'] ?? $request->file('file')?->getClientOriginalName(),
            'path' => $request->file('file')?->store('topic-sources/'.$topic->id, 'local'),
            'external_url' => $validated['external_url'] ?? null,
            'extracted_text' => $validated['text'] ?? null,
            'size' => $request->file('file')?->getSize(),
            'rights_declared' => true,
            'extraction_status' => $validated['kind'] === TopicSource::KIND_TEXT ? 'done' : 'pending',
        ]);

        if ($source->extraction_status === 'pending') {
            $this->extractor->extract($source);
        }

        return back()->with('success', 'Source added.');
    }

    /** What this will cost, before anybody commits to spending it. */
    public function estimate(Request $request, Topic $topic): JsonResponse
    {
        $validated = $request->validate([
            'language_ids' => ['required', 'array', 'min:1'],
            'language_ids.*' => ['integer', 'exists:languages,id'],
        ]);

        return response()->json($this->estimator->estimate($topic, $validated['language_ids']));
    }

    /** Sends the confirmation code for an expensive generation. */
    public function requestCode(Request $request, Topic $topic): RedirectResponse
    {
        $validated = $request->validate([
            'language_ids' => ['required', 'array', 'min:1'],
            'language_ids.*' => ['integer', 'exists:languages,id'],
        ]);

        $estimate = $this->estimator->estimate($topic, $validated['language_ids']);

        $this->codes->issue(
            $request->user(),
            OneTimeCodeService::PURPOSE_GENERATION,
            'email',
            ['topic_id' => $topic->id, 'language_ids' => $validated['language_ids'], 'cost' => $estimate['totalUsd']],
        );

        return back()->with('success', 'We have emailed you a confirmation code.');
    }

    public function generate(Request $request, Topic $topic): RedirectResponse
    {
        $validated = $request->validate([
            'language_ids' => ['required', 'array', 'min:1'],
            'language_ids.*' => ['integer', 'exists:languages,id'],
            'code' => ['nullable', 'string', 'size:6'],
        ]);

        abort_unless($topic->sources()->where('extraction_status', 'done')->exists(), 422,
            'Add at least one readable source before generating.');

        $estimate = $this->estimator->estimate($topic, $validated['language_ids']);

        // Routine generations go straight through; expensive ones need the code.
        if ($estimate['requiresOtp']) {
            if (blank($validated['code'] ?? null)) {
                return back()->withErrors([
                    'code' => 'This generation needs email confirmation. Request a code first.',
                ]);
            }

            $payload = $this->codes->verify(
                $request->user(),
                OneTimeCodeService::PURPOSE_GENERATION,
                $validated['code'],
            );

            // The code authorises this topic only, not whatever was submitted after it.
            abort_unless(($payload['topic_id'] ?? null) === $topic->id, 422,
                'That code was issued for a different topic.');
        }

        foreach ($validated['language_ids'] as $languageId) {
            $this->generator->generate($topic, Language::findOrFail($languageId));
        }

        audit('topic.generation_confirmed', $topic, [
            'languages' => count($validated['language_ids']),
            'estimated_usd' => $estimate['totalUsd'],
            'otp_used' => $estimate['requiresOtp'],
        ]);

        return back()->with('success', 'Lesson generated. It now needs a subject teacher to review it before students see it.');
    }

    public function destroySource(TopicSource $source): RedirectResponse
    {
        $source->delete();

        return back()->with('success', 'Source removed.');
    }
}

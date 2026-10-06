<?php

namespace App\Http\Controllers;

use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Services\CommunityService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityService $community,
        private readonly ModerationService $moderation,
    ) {
    }

    /** Boards are the student's own subjects — nobody wanders into a university thread. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $subjectIds = $user->subjects()->pluck('curriculum_items.id');

        $selected = $request->integer('subject');

        $questions = CommunityPost::questions()
            ->visible()
            ->whereIn('curriculum_item_id', $subjectIds)
            ->when($selected, fn ($q) => $q->where('curriculum_item_id', $selected))
            ->when($request->string('search')->toString(), fn ($q, $term) => $q->where(
                fn ($inner) => $inner->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%"),
            ))
            ->when($request->string('filter')->toString() === 'unanswered',
                fn ($q) => $q->where('replies_count', 0))
            ->with(['author:id,first_name,last_name,role', 'subject:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CommunityPost $post) => $this->present($post));

        return Inertia::render('Community/Index', [
            'questions' => $questions,
            'subjects' => $user->subjects()->get(['curriculum_items.id', 'name'])
                ->map(fn ($subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'questions' => CommunityPost::questions()->visible()
                        ->where('curriculum_item_id', $subject->id)->count(),
                ]),
            'filters' => $request->only('subject', 'search', 'filter'),
            'canPost' => $user->canParticipate(),
            'unansweredCount' => CommunityPost::questions()->visible()
                ->whereIn('curriculum_item_id', $subjectIds)->where('replies_count', 0)->count(),
        ]);
    }

    public function show(Request $request, CommunityPost $post): Response
    {
        abort_unless($post->isQuestion(), 404);

        $user = $request->user();
        $post->increment('views');
        $post->load(['author:id,first_name,last_name,role', 'subject:id,name']);

        $voted = DB::table('community_votes')->where('user_id', $user->id)
            ->pluck('community_post_id')->flip();

        return Inertia::render('Community/Show', [
            'question' => $this->present($post) + ['hasVoted' => $voted->has($post->id)],
            'replies' => $post->replies()->visible()
                ->with('author:id,first_name,last_name,role')
                ->orderByDesc('is_accepted')
                ->orderByDesc('votes')
                ->oldest()
                ->get()
                ->map(fn (CommunityPost $reply) => $this->present($reply) + [
                    'hasVoted' => $voted->has($reply->id),
                ]),
            'canPost' => $user->canParticipate(),
            'canAccept' => $post->user_id === $user->id || $user->isVerifiedTutor() || $user->isStaff(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curriculum_item_id' => [
                'required', 'integer',
                Rule::exists('academic_selections', 'curriculum_item_id')
                    ->where('user_id', $request->user()->id)
                    ->where('role', 'subject'),
            ],
            'title' => ['required', 'string', 'min:10', 'max:150'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
        ], [
            'curriculum_item_id.exists' => 'Choose one of your own subjects.',
        ]);

        $post = $this->community->ask(
            $request->user(),
            CurriculumItem::findOrFail($validated['curriculum_item_id']),
            $validated['title'],
            $validated['body'],
        );

        return redirect()->route('community.show', $post)->with('success', 'Your question has been posted.');
    }

    public function reply(Request $request, CommunityPost $post): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:5', 'max:3000']]);

        $reply = $this->community->reply($request->user(), $post, $validated['body']);

        return back()->with(
            $reply->body !== $reply->body_original ? 'warning' : 'success',
            $reply->body !== $reply->body_original
                ? 'Posted. Contact details were removed — DX keeps conversations on the platform.'
                : 'Answer posted.',
        );
    }

    public function accept(Request $request, CommunityPost $post): RedirectResponse
    {
        $this->community->accept($post, $request->user());

        return back()->with('success', 'Marked as the accepted answer.');
    }

    public function vote(Request $request, CommunityPost $post): RedirectResponse
    {
        $this->community->vote($post, $request->user());

        return back();
    }

    public function report(Request $request, CommunityPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:inappropriate,contact_details,academic_dishonesty,harassment,spam,other'],
        ]);

        $this->moderation->report($request->user(), $post, $validated['reason']);

        return back()->with('success', 'Thank you. A moderator will review this.');
    }

    private function present(CommunityPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'body' => $post->visibleBody(),
            'author' => $post->author?->name,
            'authorRole' => $post->author?->role,
            'subject' => $post->subject?->name,
            'votes' => $post->votes,
            'replies' => $post->replies_count,
            'views' => $post->views,
            'isAccepted' => $post->is_accepted,
            'isMine' => $post->user_id === request()->user()?->id,
            'askedAt' => $post->created_at?->diffForHumans(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomMember;
use App\Domains\Classroom\Models\ClassroomPost;
use App\Domains\Classroom\Services\ClassroomService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Models\Institution;
use App\Domains\Voice\Models\VoiceNote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function __construct(private readonly ClassroomService $classrooms)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $subjectIds = $user->subjects()->pluck('curriculum_items.id');
            $myClassIds = $user->classrooms()->pluck('classrooms.id');

            return Inertia::render('Classrooms/StudentIndex', [
                'classrooms' => $user->classrooms()
                    ->with(['teacher:id,first_name,last_name', 'institution:id,name', 'subject:id,name'])
                    ->withCount('students')
                    ->get()
                    ->map(fn (Classroom $c) => $this->present($c)),

                // Everything coming up, across every class they are in
                'upcoming' => app(\App\Domains\Classroom\Services\SessionService::class)
                    ->upcomingFor($user)
                    ->map(fn (\App\Domains\Classroom\Models\ClassSession $session) => [
                        'id' => $session->id,
                        'title' => $session->title,
                        'classroom' => $session->classroom?->name,
                        'classroomId' => $session->classroom_id,
                        'whenLabel' => $session->whenLabel(),
                        'mode' => $session->mode,
                        'location' => $session->location,
                        'isJoinable' => $session->isJoinable(),
                    ]),

                // A code is not the only way in: open classes in their own
                // subjects are listed so a learner can find one themselves
                'discoverable' => Classroom::discoverable()
                    ->whereNotIn('id', $myClassIds)
                    ->when($subjectIds->isNotEmpty(), fn ($q) => $q->whereIn('curriculum_item_id', $subjectIds))
                    ->with(['teacher:id,first_name,last_name', 'subject:id,name'])
                    ->withCount('students')
                    ->limit(12)
                    ->get()
                    ->map(fn (Classroom $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'about' => $c->about,
                        'teacher' => $c->teacher?->name,
                        'subject' => $c->subject?->name,
                        'students' => $c->students_count,
                        'nextSession' => $c->sessions()->upcoming()->first()?->whenLabel(),
                    ]),
            ]);
        }

        abort_unless($user->isTutor() || $user->isStaff(), 403);

        return Inertia::render('Classrooms/TeacherIndex', [
            'classrooms' => Classroom::where('teacher_id', $user->id)
                ->with(['institution:id,name', 'subject:id,name'])
                ->withCount('students')
                ->latest()
                ->get()
                ->map(fn (Classroom $c) => $this->present($c) + [
                    'joinCode' => $c->join_code,
                    'joinCodeActive' => $c->join_code_active,
                    'schoolLinkStatus' => $c->school_link_status,
                    'schoolLinkNotes' => $c->school_link_notes,
                    'isArchived' => $c->is_archived,
                ]),
            'institutions' => Institution::orderBy('name')->get(['id', 'name', 'type', 'city']),
            'subjects' => $user->subjects()->get(['curriculum_items.id', 'name']),
            'canCreate' => $user->isVerifiedTutor() || $user->isStaff(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isVerifiedTutor() || $user->isStaff(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in([Classroom::TYPE_PERSONAL, Classroom::TYPE_SCHOOL])],
            'institution_id' => ['nullable', 'required_if:type,school', 'integer', 'exists:institutions,id'],
            'curriculum_item_id' => [
                'nullable', 'integer',
                Rule::exists('curriculum_items', 'id')->where('type', CurriculumItem::TYPE_SUBJECT),
            ],
            'capacity' => ['nullable', 'integer', 'between:2,500'],
        ], [
            'institution_id.required_if' => 'Choose the school, college or university this class belongs to.',
        ]);

        $classroom = $this->classrooms->create($user, $validated);

        return back()->with('success', $classroom->type === Classroom::TYPE_SCHOOL
            ? "Class created. Students can join with code {$classroom->join_code}. The school name appears once DX approves the link."
            : "Class created. Students can join with code {$classroom->join_code}.");
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        $user = $request->user();

        abort_unless($this->canView($user, $classroom), 403);

        $isTeacher = $classroom->teacher_id === $user->id;

        $classroom->load(['teacher:id,first_name,last_name', 'institution:id,name', 'subject:id,name']);

        $user = $request->user();

        return Inertia::render('Classrooms/Show', [
            // The schedule is the point of a class: without it this page is a
            // noticeboard and nobody knows when to turn up.
            'sessions' => $classroom->sessions()
                ->where('starts_at', '>=', now()->subWeek())
                ->orderBy('starts_at')
                ->with('attendees:id')
                ->get()
                ->map(fn (\App\Domains\Classroom\Models\ClassSession $session) => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'description' => $session->description,
                    'mode' => $session->mode,
                    'meetingUrl' => $session->isJoinable() ? $session->meeting_url : null,
                    'location' => $session->location,
                    'startsAt' => $session->starts_at->toIso8601String(),
                    'whenLabel' => $session->whenLabel(),
                    'durationMinutes' => $session->duration_minutes,
                    'status' => $session->status,
                    'cancelReason' => $session->cancel_reason,
                    'isJoinable' => $session->isJoinable(),
                    'isPast' => $session->isPast(),
                    'repeats' => $session->repeats ?? ($session->parent_session_id ? 'weekly' : null),
                    'goingCount' => $session->attendees->count(),
                    'myResponse' => $session->attendees->firstWhere('id', $user->id)?->pivot?->response,
                ]),
            'classroom' => $this->present($classroom) + [
                'description' => $classroom->description,
                'about' => $classroom->about,
                'isDiscoverable' => (bool) $classroom->is_discoverable,
                'joinCode' => $isTeacher ? $classroom->join_code : null,
                'joinCodeActive' => $classroom->join_code_active,
                'schoolLinkStatus' => $classroom->school_link_status,
            ],
            'isTeacher' => $isTeacher,
            'posts' => $classroom->posts()
                ->topLevel()
                ->where('is_removed', false)
                ->with([
                    'author:id,first_name,last_name',
                    'resource:id,title',
                    'replies' => fn ($q) => $q->where('is_removed', false)->with('author:id,first_name,last_name'),
                ])
                ->latest()
                ->get()
                ->map(fn (ClassroomPost $post) => [
                    'id' => $post->id,
                    'type' => $post->type,
                    'title' => $post->title,
                    'body' => $post->body,
                    'author' => $post->author?->name,
                    'isMine' => $post->author_id === $user->id,
                    'resource' => $post->resource ? ['id' => $post->resource->id, 'title' => $post->resource->title] : null,
                    'dueAt' => $post->due_at?->toFormattedDateString(),
                    'isOverdue' => $post->isOverdue(),
                    'completed' => $post->completedBy()->where('users.id', $user->id)->exists(),
                    'completedCount' => $isTeacher ? $post->completedBy()->count() : null,
                    'postedAt' => $post->created_at?->diffForHumans(),
                    'replies' => $post->replies->map(fn (ClassroomPost $reply) => [
                        'id' => $reply->id,
                        'body' => $reply->body,
                        'author' => $reply->author?->name,
                        'isTeacher' => $reply->author_id === $classroom->teacher_id,
                        'postedAt' => $reply->created_at?->diffForHumans(),
                    ]),
                ]),
            'members' => $isTeacher
                ? $classroom->students()->get(['users.id', 'first_name', 'last_name'])
                    ->map(fn (User $student) => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'joinedAt' => $student->pivot->joined_at
                            ? \Illuminate\Support\Carbon::parse($student->pivot->joined_at)->toFormattedDateString()
                            : null,
                    ])
                : [],
            'memberCount' => $classroom->students()->count(),
            'voiceNotes' => VoiceNote::where('attachable_type', $classroom->getMorphClass())
                ->where('attachable_id', $classroom->id)
                ->with('user:id,first_name,last_name')
                ->latest()
                ->get()
                ->map(fn (VoiceNote $note) => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'author' => $note->user?->name,
                    'duration' => $note->durationLabel(),
                    'transcript' => $note->transcript,
                    'status' => $note->transcription_status,
                    'createdAt' => $note->created_at?->diffForHumans(),
                ]),
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:12'],
        ]);

        $classroom = Classroom::active()
            ->where('join_code', strtoupper($validated['join_code']))
            ->first();

        if (! $classroom) {
            return back()->withErrors(['join_code' => 'We could not find a class with that code.']);
        }

        $this->classrooms->join($classroom, $request->user());

        return redirect()->route('classrooms.show', $classroom)
            ->with('success', 'You have joined '.$classroom->displayName().'.');
    }

    public function leave(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->classrooms->leave($classroom, $request->user());

        return redirect()->route('classrooms.index')->with('success', 'You have left the class.');
    }

    public function removeMember(Request $request, Classroom $classroom, User $user): RedirectResponse
    {
        abort_unless($classroom->teacher_id === $request->user()->id || $request->user()->isStaff(), 403);

        $this->classrooms->remove($classroom, $user, $request->user());

        return back()->with('success', 'Student removed from the class.');
    }

    public function rotateCode(Request $request, Classroom $classroom): RedirectResponse
    {
        abort_unless($classroom->teacher_id === $request->user()->id, 403);

        $code = $this->classrooms->rotateCode($classroom);

        return back()->with('success', "New join code: {$code}. The old one no longer works.");
    }

    public function post(Request $request, Classroom $classroom): RedirectResponse
    {
        $user = $request->user();
        $isTeacher = $classroom->teacher_id === $user->id;

        // Students may ask; only the teacher posts notes and sets tasks
        abort_unless(
            $isTeacher || $classroom->students()->whereKey($user->id)->exists(),
            403,
        );

        $validated = $request->validate([
            'type' => ['required', Rule::in($isTeacher
                ? [ClassroomPost::TYPE_NOTE, ClassroomPost::TYPE_TASK]
                : [ClassroomPost::TYPE_QUESTION])],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);

        $post = $this->createPost($classroom, $user, $validated, $isTeacher);

        $this->notifyAboutPost($classroom, $post, $user, $isTeacher);

        return back()->with('success', $isTeacher
            ? 'Posted to the class.'
            : 'Your question is on the class page. Your teacher will see it.');
    }

    public function complete(Request $request, Classroom $classroom, ClassroomPost $post): RedirectResponse
    {
        abort_unless($post->classroom_id === $classroom->id, 404);
        abort_unless($this->isMember($request->user(), $classroom), 403);

        $post->completedBy()->syncWithoutDetaching([
            $request->user()->id => ['completed_at' => now()],
        ]);

        return back();
    }

    private function canView(User $user, Classroom $classroom): bool
    {
        return $classroom->teacher_id === $user->id
            || $user->isStaff()
            || $this->isMember($user, $classroom);
    }

    private function isMember(User $user, Classroom $classroom): bool
    {
        return ClassroomMember::where('classroom_id', $classroom->id)
            ->where('user_id', $user->id)
            ->where('status', ClassroomMember::STATUS_ACTIVE)
            ->exists();
    }

    private function present(Classroom $classroom): array
    {
        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'displayName' => $classroom->displayName(),
            'type' => $classroom->type,
            'school' => $classroom->showsSchoolName() ? $classroom->institution?->name : null,
            'pendingSchool' => $classroom->type === Classroom::TYPE_SCHOOL
                && $classroom->school_link_status === Classroom::LINK_PENDING
                ? $classroom->institution?->name
                : null,
            'subject' => $classroom->subject?->name,
            'teacher' => $classroom->teacher?->name,
            'students' => $classroom->students_count ?? $classroom->students()->count(),
            'capacity' => $classroom->capacity,
        ];
    }

    /** A teacher can list the class so students in that subject can find it. */
    public function setDiscoverable(Request $request, Classroom $classroom): \Illuminate\Http\RedirectResponse
    {
        abort_unless($classroom->teacher_id === $request->user()->id, 403);

        $validated = $request->validate([
            'is_discoverable' => ['required', 'boolean'],
            'about' => ['nullable', 'string', 'max:500'],
        ]);

        $classroom->update([
            'is_discoverable' => $validated['is_discoverable'],
            'about' => $validated['about'] ?? $classroom->about,
        ]);

        return back()->with('success', $validated['is_discoverable']
            ? 'Students studying this subject can now find your class.'
            : 'Your class is private again. Only your join code works.');
    }

    /** Joining an open class, without needing a code from the teacher. */
    public function requestJoin(Request $request, Classroom $classroom): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($classroom->is_discoverable && ! $classroom->is_archived, 404);

        if (! $user->canParticipate()) {
            return back()->withErrors([
                'join' => 'Your parent or guardian needs to approve your account before you can join a class.',
            ]);
        }

        if ($classroom->students()->whereKey($user->id)->exists()) {
            return redirect()->route('classrooms.show', $classroom);
        }

        if ($classroom->capacity && $classroom->students()->count() >= $classroom->capacity) {
            return back()->withErrors(['join' => 'That class is full.']);
        }

        $classroom->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['status' => \App\Domains\Classroom\Models\ClassroomMember::STATUS_ACTIVE, 'joined_at' => now()],
        );

        audit('classroom.joined', $classroom, ['via' => 'discovery']);

        return redirect()->route('classrooms.show', $classroom)
            ->with('success', "You have joined {$classroom->name}.");
    }

    /** A reply on a class post, from the teacher or any member. */
    public function reply(Request $request, Classroom $classroom, ClassroomPost $post): RedirectResponse
    {
        $user = $request->user();

        abort_unless($post->classroom_id === $classroom->id, 404);
        abort_unless(
            $classroom->teacher_id === $user->id || $classroom->students()->whereKey($user->id)->exists(),
            403,
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $reply = $this->createPost($classroom, $user, [
            'type' => ClassroomPost::TYPE_QUESTION,
            'title' => null,
            'body' => $validated['body'],
            'parent_id' => $post->id,
        ], $classroom->teacher_id === $user->id);

        $post->increment('replies_count');

        $this->notifyAboutReply($classroom, $post, $reply, $user);

        return back()->with('success', 'Reply posted.');
    }

    /**
     * Writes a post, masking contact details in anything a student wrote.
     * The original is kept for moderators, as everywhere else on the platform.
     */
    private function createPost(Classroom $classroom, \App\Models\User $author, array $data, bool $isTeacher): ClassroomPost
    {
        $body = $data['body'];
        $original = null;

        if (! $isTeacher) {
            $filter = app(\App\Domains\Tutoring\Services\ContentFilter::class);
            $result = $filter->mask($body);

            if ($result['masked'] || $filter->shouldFlag($body)) {
                $original = $body;
                $body = $result['body'];
            }
        }

        return $classroom->posts()->create([
            'author_id' => $author->id,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'body' => $body,
            'body_original' => $original,
            'is_flagged' => $original !== null,
            'parent_id' => $data['parent_id'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ]);
    }

    /**
     * A new question goes to the teacher. A teacher's note or task goes to the
     * whole class, because that is what it is for.
     */
    private function notifyAboutPost(
        Classroom $classroom,
        ClassroomPost $post,
        \App\Models\User $author,
        bool $isTeacher,
    ): void {
        if ($isTeacher) {
            $recipients = $classroom->students()->get();
            $headline = $post->type === ClassroomPost::TYPE_TASK
                ? "New task in {$classroom->name}"
                : "New post in {$classroom->name}";
        } else {
            // Only the teacher: a class of thirty does not need telling that
            // one of them asked something
            $recipients = collect([$classroom->teacher])->filter();
            $headline = "New question in {$classroom->name}";
        }

        if ($recipients->isEmpty()) {
            return;
        }

        \Illuminate\Support\Facades\Notification::send(
            $recipients,
            new \App\Notifications\ClassPostActivity($post, $headline, $author->first_name),
        );
    }

    /**
     * A reply reaches the people in that conversation: whoever asked, anyone
     * who already replied, and the teacher. Not the whole class.
     */
    private function notifyAboutReply(
        Classroom $classroom,
        ClassroomPost $post,
        ClassroomPost $reply,
        \App\Models\User $author,
    ): void {
        $participantIds = $post->replies()->pluck('author_id')
            ->push($post->author_id)
            ->push($classroom->teacher_id)
            ->unique()
            ->reject(fn ($id) => $id === $author->id)
            ->values();

        if ($participantIds->isEmpty()) {
            return;
        }

        $recipients = \App\Models\User::whereIn('id', $participantIds)->get();

        \Illuminate\Support\Facades\Notification::send(
            $recipients,
            new \App\Notifications\ClassPostActivity(
                $post,
                $author->id === $classroom->teacher_id
                    ? "Your teacher replied in {$classroom->name}"
                    : "New reply in {$classroom->name}",
                $author->first_name,
            ),
        );
    }

    /**
     * Reporting a class post.
     *
     * Deletion stays with moderators — nothing in a class can be erased before
     * it has been seen, which matters on a platform used by minors. So the
     * route for a post someone regrets, or one a teacher wants gone, is to put
     * it in front of a moderator rather than to remove it.
     */
    public function reportPost(Request $request, Classroom $classroom, ClassroomPost $post): RedirectResponse
    {
        $user = $request->user();

        abort_unless($post->classroom_id === $classroom->id, 404);
        abort_unless(
            $classroom->teacher_id === $user->id || $classroom->students()->whereKey($user->id)->exists(),
            403,
        );

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:inappropriate,contact_details,academic_dishonesty,harassment,spam,other'],
        ]);

        app(\App\Domains\Tutoring\Services\ModerationService::class)
            ->report($user, $post, $validated['reason']);

        return back()->with('success', 'Thank you. A moderator will review this.');
    }
}
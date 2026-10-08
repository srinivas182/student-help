<?php

namespace App\Http\Controllers;

use App\Domains\Classroom\Models\ClassSession;
use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassSessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions)
    {
    }

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'mode' => ['required', Rule::in([ClassSession::MODE_ONLINE, ClassSession::MODE_IN_PERSON])],
            'meeting_url' => ['nullable', 'required_if:mode,online', 'url', 'max:500', new \App\Rules\SafeUrl],
            'location' => ['nullable', 'required_if:mode,in_person', 'string', 'max:200'],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:15,300'],
            'repeats' => ['nullable', Rule::in(['weekly'])],
            'repeats_until' => ['nullable', 'required_if:repeats,weekly', 'date', 'after:starts_at'],
        ], [
            'starts_at.after' => 'Pick a time in the future.',
            'meeting_url.required_if' => 'Add the Zoom, Meet or Teams link so learners can join.',
            'location.required_if' => 'Tell learners where to come.',
        ]);

        $this->sessions->schedule($classroom, $request->user(), $validated);

        return back()->with('success', 'Session scheduled. Everyone in the class has been told.');
    }

    public function update(Request $request, ClassSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'mode' => ['required', Rule::in([ClassSession::MODE_ONLINE, ClassSession::MODE_IN_PERSON])],
            'meeting_url' => ['nullable', 'required_if:mode,online', 'url', 'max:500', new \App\Rules\SafeUrl],
            'location' => ['nullable', 'required_if:mode,in_person', 'string', 'max:200'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'between:15,300'],
            'whole_series' => ['boolean'],
        ]);

        $this->sessions->update(
            $session,
            $request->user(),
            $validated,
            $request->boolean('whole_series'),
        );

        return back()->with('success', 'Session updated.');
    }

    public function cancel(Request $request, ClassSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            'whole_series' => ['boolean'],
        ], [
            'reason.required' => 'Tell learners why, so they are not left guessing.',
        ]);

        $this->sessions->cancel(
            $session,
            $request->user(),
            $validated['reason'],
            $request->boolean('whole_series'),
        );

        return back()->with('success', 'Cancelled, and everyone has been told.');
    }

    public function respond(Request $request, ClassSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'response' => ['required', Rule::in(['going', 'not_going'])],
        ]);

        $this->sessions->respond($session, $request->user(), $validated['response']);

        return back();
    }

    /** Records attendance, then sends the student to the meeting. */
    public function join(Request $request, ClassSession $session): RedirectResponse
    {
        abort_unless(
            $session->classroom->students()->whereKey($request->user()->id)->exists()
                || $session->classroom->teacher_id === $request->user()->id,
            403,
        );

        abort_unless($session->isJoinable(), 422, 'This session is not open yet.');

        $this->sessions->markAttended($session, $request->user());

        if ($session->mode === ClassSession::MODE_ONLINE && $session->meeting_url) {
            return redirect()->away($session->meeting_url);
        }

        return back()->with('success', 'Marked as attending. See you there.');
    }
}

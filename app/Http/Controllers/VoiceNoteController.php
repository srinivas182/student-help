<?php

namespace App\Http\Controllers;

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomMember;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Voice\Models\VoiceNote;
use App\Domains\Voice\Services\VoiceNoteService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoiceNoteController extends Controller
{
    public function __construct(private readonly VoiceNoteService $voiceNotes)
    {
    }

    /**
     * Teachers record a spoken explanation, either for a class or inside a
     * help request. Students may reply with audio in their own request.
     */
    public function store(Request $request): RedirectResponse
    {
        $maxMb = (int) setting('max_attachment_mb', 10);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/webm,audio/mp4,audio/mpeg,audio/ogg,video/webm', 'max:'.($maxMb * 1024)],
            'duration_seconds' => ['required', 'integer', 'between:1,'.VoiceNoteService::MAX_SECONDS],
            'title' => ['nullable', 'string', 'max:120'],
            'context' => ['required', Rule::in(['classroom', 'request'])],
            'context_id' => ['required', 'integer'],
        ], [
            'duration_seconds.between' => 'Voice notes can be up to 10 minutes long.',
            'audio.max' => "Recordings must be {$maxMb} MB or smaller.",
        ]);

        $attachable = $this->resolveContext($request->user(), $validated['context'], (int) $validated['context_id']);

        $this->voiceNotes->store($request->user(), $request->file('audio'), $attachable, [
            'duration_seconds' => (int) $validated['duration_seconds'],
            'title' => $validated['title'] ?? null,
        ]);

        return back()->with('success', 'Voice note shared.');
    }

    /** Audio is streamed from private storage; nothing is publicly linkable. */
    public function play(Request $request, VoiceNote $voiceNote): StreamedResponse
    {
        abort_unless($this->canAccess($request->user(), $voiceNote), 403);
        abort_unless(Storage::disk('local')->exists($voiceNote->path), 404);

        $voiceNote->increment('plays');

        return Storage::disk('local')->response($voiceNote->path, $voiceNote->title ?? 'voice-note');
    }

    public function destroy(Request $request, VoiceNote $voiceNote): RedirectResponse
    {
        abort_unless($voiceNote->user_id === $request->user()->id || $request->user()->isStaff(), 403);

        $this->voiceNotes->delete($voiceNote);

        return back()->with('success', 'Voice note removed.');
    }

    private function resolveContext(User $user, string $context, int $id)
    {
        if ($context === 'classroom') {
            $classroom = Classroom::findOrFail($id);

            abort_unless($classroom->teacher_id === $user->id, 403);

            return $classroom;
        }

        $helpRequest = HelpRequest::findOrFail($id);

        abort_unless(in_array($user->id, [$helpRequest->student_id, $helpRequest->tutor_id], true), 403);
        abort_unless($helpRequest->isConversationOpen(), 422);
        abort_unless($user->canParticipate(), 403);

        return $helpRequest;
    }

    private function canAccess(User $user, VoiceNote $note): bool
    {
        if ($user->isStaff() || $note->user_id === $user->id) {
            return true;
        }

        if ($note->attachable_type === Classroom::class) {
            return ClassroomMember::where('classroom_id', $note->attachable_id)
                ->where('user_id', $user->id)
                ->where('status', ClassroomMember::STATUS_ACTIVE)
                ->exists();
        }

        if ($note->attachable_type === HelpRequest::class) {
            $helpRequest = HelpRequest::find($note->attachable_id);

            return $helpRequest
                && in_array($user->id, [$helpRequest->student_id, $helpRequest->tutor_id], true);
        }

        return false;
    }
}

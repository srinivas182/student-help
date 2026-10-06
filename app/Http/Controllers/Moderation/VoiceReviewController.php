<?php

namespace App\Http\Controllers\Moderation;

use App\Domains\Voice\Models\VoiceNote;
use App\Domains\Voice\Services\VoiceNoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moderators review audio the same way they review text.
 *
 * Where a transcript exists they can read it, with the unmasked version shown
 * alongside. Where it does not, they listen — the queue says which, so nothing
 * is quietly unreviewed.
 */
class VoiceReviewController extends Controller
{
    public function __construct(private readonly VoiceNoteService $voiceNotes)
    {
    }

    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString() ?: 'flagged';

        $query = VoiceNote::with(['user:id,first_name,last_name,role'])
            ->when($filter === 'flagged', fn ($q) => $q->where('is_flagged', true))
            ->when($filter === 'untranscribed', fn ($q) => $q->whereIn('transcription_status', [
                VoiceNote::STATUS_SKIPPED, VoiceNote::STATUS_FAILED, VoiceNote::STATUS_PENDING,
            ]));

        return Inertia::render('Moderation/VoiceNotes', [
            'notes' => $query->latest()->paginate(15)->withQueryString()->through(fn (VoiceNote $note) => [
                'id' => $note->id,
                'title' => $note->title,
                'author' => $note->user?->name,
                'role' => $note->user?->role,
                'duration' => $note->durationLabel(),
                'status' => $note->transcription_status,
                'transcript' => $note->transcript,
                'original' => $note->transcript_original,
                'wasMasked' => $note->transcript !== $note->transcript_original,
                'isFlagged' => $note->is_flagged,
                'flagReason' => $note->flag_reason,
                'context' => $note->attachable_type ? class_basename($note->attachable_type) : null,
                'plays' => $note->plays,
                'createdAt' => $note->created_at?->diffForHumans(),
            ]),
            'filters' => ['filter' => $filter],
            'counts' => [
                'flagged' => VoiceNote::where('is_flagged', true)->count(),
                'untranscribed' => VoiceNote::whereIn('transcription_status', [
                    VoiceNote::STATUS_SKIPPED, VoiceNote::STATUS_FAILED, VoiceNote::STATUS_PENDING,
                ])->count(),
                'all' => VoiceNote::count(),
            ],
            'transcriptionEnabled' => app(\App\Domains\Voice\Services\Transcriber::class)->isEnabled(),
        ]);
    }

    public function clear(Request $request, VoiceNote $voiceNote): RedirectResponse
    {
        $voiceNote->update(['is_flagged' => false, 'flag_reason' => null]);

        audit('voice_note.cleared', $voiceNote, ['moderator_id' => $request->user()->id]);

        return back()->with('success', 'Cleared.');
    }

    public function remove(Request $request, VoiceNote $voiceNote): RedirectResponse
    {
        $this->voiceNotes->delete($voiceNote);

        return back()->with('success', 'Voice note removed.');
    }
}

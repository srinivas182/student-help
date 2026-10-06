<?php

namespace App\Domains\Voice\Services;

use App\Domains\Tutoring\Services\ContentFilter;
use App\Domains\Voice\Jobs\TranscribeVoiceNote;
use App\Domains\Voice\Models\VoiceNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VoiceNoteService
{
    public const MAX_SECONDS = 600;

    public function __construct(
        private readonly ContentFilter $filter,
        private readonly Transcriber $transcriber,
    ) {
    }

    public function store(User $author, UploadedFile $file, ?Model $attachable = null, array $meta = []): VoiceNote
    {
        $note = VoiceNote::create([
            'user_id' => $author->id,
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
            'path' => $file->store('voice-notes/'.$author->id, 'local'),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'duration_seconds' => $meta['duration_seconds'] ?? null,
            'title' => $meta['title'] ?? null,
            'transcription_status' => $this->transcriber->isEnabled()
                ? VoiceNote::STATUS_PENDING
                : VoiceNote::STATUS_SKIPPED,
        ]);

        if ($this->transcriber->isEnabled()) {
            TranscribeVoiceNote::dispatch($note->id);
        }

        audit('voice_note.created', $note, [
            'attached_to' => $attachable?->getMorphClass(),
            'duration' => $note->duration_seconds,
        ]);

        return $note;
    }

    /** Runs the same masking and flagging over speech that we apply to text. */
    public function applyTranscript(VoiceNote $note, string $text): void
    {
        $masked = $this->filter->mask($text);

        $note->update([
            'transcript' => $masked['body'],
            'transcript_original' => $text,
            'transcription_status' => VoiceNote::STATUS_DONE,
            'transcribed_at' => now(),
            'is_flagged' => $masked['masked'] || $this->filter->shouldFlag($text),
            'flag_reason' => $masked['masked']
                ? 'contact_details_spoken'
                : ($this->filter->shouldFlag($text) ? 'prohibited_words' : null),
        ]);

        if ($note->is_flagged) {
            audit('voice_note.flagged', $note, ['reason' => $note->flag_reason]);
        }
    }

    public function markFailed(VoiceNote $note): void
    {
        $note->update(['transcription_status' => VoiceNote::STATUS_FAILED]);
    }

    public function delete(VoiceNote $note): void
    {
        Storage::disk('local')->delete($note->path);

        audit('voice_note.deleted', $note);

        $note->delete();
    }
}

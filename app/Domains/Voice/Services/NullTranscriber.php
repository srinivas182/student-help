<?php

namespace App\Domains\Voice\Services;

use App\Domains\Voice\Models\VoiceNote;

/**
 * The default until DX chooses a transcription provider.
 *
 * Voice notes still work and are still moderatable — a moderator listens to the
 * recording itself. Nothing is silently dropped: the note is marked "skipped"
 * so it is obvious in the moderation queue that no transcript exists.
 */
class NullTranscriber implements Transcriber
{
    public function transcribe(VoiceNote $note): ?array
    {
        return null;
    }

    public function isEnabled(): bool
    {
        return false;
    }
}

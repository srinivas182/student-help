<?php

namespace App\Domains\Voice\Services;

use App\Domains\Voice\Models\VoiceNote;

/**
 * Turning speech into text, so a moderator can read a voice note instead of
 * listening to every one.
 *
 * The provider is deliberately behind an interface: DX can start with no
 * transcription at all (recordings still work, moderators listen), and switch
 * on a paid provider later without touching the upload, playback or moderation
 * code. Per-minute costs are the reason this is a decision for DX, not us.
 */
interface Transcriber
{
    /** @return array{text: string, provider: string}|null */
    public function transcribe(VoiceNote $note): ?array;

    public function isEnabled(): bool;
}

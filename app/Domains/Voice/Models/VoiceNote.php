<?php

namespace App\Domains\Voice\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VoiceNote extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'user_id', 'attachable_type', 'attachable_id', 'path', 'mime_type', 'size',
        'duration_seconds', 'title', 'transcription_status', 'transcript',
        'transcript_original', 'is_flagged', 'flag_reason', 'transcribed_at', 'plays',
    ];

    /** The unmasked transcript never leaves the server except for moderators. */
    protected $hidden = ['transcript_original', 'path'];

    protected function casts(): array
    {
        return ['is_flagged' => 'boolean', 'transcribed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function durationLabel(): string
    {
        $seconds = (int) ($this->duration_seconds ?? 0);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function isTranscribed(): bool
    {
        return $this->transcription_status === self::STATUS_DONE;
    }
}

<?php

namespace App\Domains\Tutor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicProgress extends Model
{
    use HasFactory;

    protected $table = 'topic_progress';

    protected $fillable = [
        'user_id', 'topic_id', 'topic_version_id', 'last_segment',
        'segments_done', 'started_at', 'completed_at', 'seconds_spent',
    ];

    protected function casts(): array
    {
        return [
            'segments_done' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function percent(int $totalSegments): int
    {
        if ($totalSegments === 0) {
            return 0;
        }

        return (int) round(count($this->segments_done ?? []) / $totalSegments * 100);
    }
}

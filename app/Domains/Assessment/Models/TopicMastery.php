<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Tutor\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicMastery extends Model
{
    use HasFactory;

    protected $table = 'topic_mastery';

    protected $fillable = [
        'user_id', 'topic_id', 'highest_level', 'best_percent',
        'review_stage', 'next_review_at', 'last_attempt_at',
    ];

    protected function casts(): array
    {
        return ['next_review_at' => 'datetime', 'last_attempt_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function scopeDueForReview(Builder $query): Builder
    {
        return $query->whereNotNull('next_review_at')->where('next_review_at', '<=', now());
    }
}

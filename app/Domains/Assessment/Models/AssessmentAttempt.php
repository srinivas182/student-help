<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Tutor\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'topic_id', 'topic_version_id', 'level', 'score', 'total',
        'percent', 'passed', 'is_review', 'answers', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'is_review' => 'boolean', 'answers' => 'array', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}

<?php

namespace App\Domains\Tutor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_REVIEW = 'review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    public const LEVELS = [
        'basic' => 'Basic',
        'easy' => 'Easy',
        'intermediate' => 'Intermediate',
        'difficult' => 'Difficult',
        'extreme' => 'Extremely difficult',
    ];

    protected $fillable = [
        'topic_id', 'language_id', 'status', 'lesson', 'notes', 'flashcards',
        'provider', 'model', 'cost_usd', 'reviewed_by', 'reviewed_at',
        'review_notes', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'lesson' => 'array',
            'flashcards' => 'array',
            'reviewed_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TopicQuestion::class)->orderBy('position');
    }

    public function segments(): array
    {
        return $this->lesson['segments'] ?? [];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** Shown on every lesson: AI origin plus the human who checked it. */
    public function provenance(): array
    {
        return [
            'aiGenerated' => $this->provider !== null,
            'reviewer' => $this->reviewer?->name,
            'reviewerQualification' => $this->reviewer?->tutorProfile?->highest_qualification,
            'reviewedAt' => $this->reviewed_at?->toFormattedDateString(),
        ];
    }
}

<?php

namespace App\Domains\Tutor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_version_id', 'level', 'question', 'options', 'correct_index', 'explanation', 'position',
    ];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TopicVersion::class, 'topic_version_id');
    }

    /** Distractors carry the misconception they represent, so feedback can name it. */
    public function misconceptionFor(int $index): ?string
    {
        return $this->options[$index]['misconception'] ?? null;
    }
}

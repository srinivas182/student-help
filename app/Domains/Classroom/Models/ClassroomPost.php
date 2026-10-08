<?php

namespace App\Domains\Classroom\Models;

use App\Domains\Content\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassroomPost extends Model
{
    use HasFactory;

    public const TYPE_QUESTION = 'question';

    public const TYPE_NOTE = 'note';
    public const TYPE_TASK = 'task';

    protected $fillable = [
        'classroom_id', 'author_id', 'type', 'title', 'body', 'resource_id', 'due_at', 'parent_id', 'body_original', 'is_flagged', 'is_removed',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'is_flagged' => 'boolean', 'is_removed' => 'boolean'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function completedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_post_completions')
            ->withPivot('completed_at');
    }

    public function isOverdue(): bool
    {
        return $this->type === self::TYPE_TASK && $this->due_at !== null && $this->due_at->isPast();
    }

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function scopeTopLevel(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('parent_id');
    }
}
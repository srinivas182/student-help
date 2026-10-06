<?php

namespace App\Domains\Community\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_item_id', 'user_id', 'parent_id', 'title', 'body', 'body_original',
        'is_flagged', 'is_removed', 'is_accepted', 'votes', 'replies_count', 'views',
    ];

    protected $hidden = ['body_original'];

    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'is_removed' => 'boolean',
            'is_accepted' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumItem::class, 'curriculum_item_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeQuestions(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_removed', false);
    }

    public function isQuestion(): bool
    {
        return $this->parent_id === null;
    }

    public function visibleBody(): string
    {
        return $this->is_removed ? '[removed by a moderator]' : $this->body;
    }
}

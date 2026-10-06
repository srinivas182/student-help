<?php

namespace App\Domains\StudyGroup\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyGroup extends Model
{
    use HasFactory;

    /** Reports needed before a group is flagged for a moderator automatically. */
    public const AUTO_FLAG_THRESHOLD = 3;

    protected $fillable = [
        'owner_id', 'curriculum_item_id', 'name', 'description', 'join_code',
        'is_open', 'capacity', 'report_count', 'is_flagged', 'is_locked', 'locked_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'is_flagged' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumItem::class, 'curriculum_item_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'study_group_members')
            ->wherePivot('status', 'active')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StudyGroupMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StudyGroupMessage::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_locked', false)->where('is_open', true);
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= $this->capacity;
    }

    public function canAcceptJoins(): bool
    {
        return ! $this->is_locked && $this->is_open && ! $this->isFull();
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('status', StudyGroupMember::STATUS_ACTIVE)
            ->exists();
    }
}

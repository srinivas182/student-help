<?php

namespace App\Domains\Classroom\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;

    public const TYPE_PERSONAL = 'personal';
    public const TYPE_SCHOOL = 'school';

    public const LINK_PENDING = 'pending';
    public const LINK_APPROVED = 'approved';
    public const LINK_REJECTED = 'rejected';

    protected $fillable = [
        'teacher_id', 'name', 'description', 'type', 'institution_id', 'school_link_status',
        'school_link_notes', 'curriculum_item_id', 'join_code', 'join_code_active',
        'capacity', 'is_archived',
    ];

    protected function casts(): array
    {
        return ['join_code_active' => 'boolean', 'is_archived' => 'boolean'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumItem::class, 'curriculum_item_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClassroomMember::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_members')
            ->wherePivot('status', ClassroomMember::STATUS_ACTIVE)
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ClassroomPost::class)->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /** The school's name is only shown once DX has approved the link. */
    public function showsSchoolName(): bool
    {
        return $this->type === self::TYPE_SCHOOL
            && $this->school_link_status === self::LINK_APPROVED
            && $this->institution_id !== null;
    }

    public function displayName(): string
    {
        return $this->showsSchoolName()
            ? $this->institution->name.' · '.$this->name
            : $this->name;
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->students()->count() >= $this->capacity;
    }

    public function canAcceptJoins(): bool
    {
        return ! $this->is_archived && $this->join_code_active && ! $this->isFull();
    }
}

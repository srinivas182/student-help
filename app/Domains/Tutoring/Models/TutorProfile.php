<?php

namespace App\Domains\Tutoring\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TutorProfile extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'bio', 'highest_qualification', 'institution_name', 'languages',
        'availability', 'verification_status', 'verification_notes', 'reviewed_by',
        'reviewed_at', 'is_available', 'average_rating', 'ratings_count', 'resolved_count',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'availability' => 'array',
            'is_available' => 'boolean',
            'reviewed_at' => 'datetime',
            'average_rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumItem::class, 'tutor_subjects');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function isApproved(): bool
    {
        return $this->verification_status === self::STATUS_APPROVED;
    }

    /** TUT-06 / REQ-03: only approved, active tutors are offered requests. */
    public function isMatchable(): bool
    {
        return $this->isApproved() && $this->is_available;
    }
}

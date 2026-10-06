<?php

namespace App\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Models\Institution;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\TutorProfile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_STUDENT = 'student';
    public const ROLE_TUTOR = 'tutor';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const MINOR_AGE = 18;

    protected $fillable = [
        'first_name', 'last_name', 'name', 'email', 'password', 'date_of_birth',
        'role', 'status', 'mobile', 'institution_id', 'onboarding_completed_at',
        'plan', 'plan_expires_at', 'free_for_life', 'monthly_request_quota',
        'notification_preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'date_of_birth' => 'date',
            'free_for_life' => 'boolean',
            'notification_preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    // ----------------------------------------------------------------- relations

    public function guardianConsent(): HasOne
    {
        return $this->hasOne(GuardianConsent::class)->latestOfMany();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function tutorProfile(): HasOne
    {
        return $this->hasOne(TutorProfile::class);
    }

    public function helpRequests(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'student_id');
    }

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'tutor_id');
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Classroom\Models\Classroom::class, 'classroom_members')
            ->wherePivot('status', 'active')
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    /** Pathway, institution type, grade, faculty — the student's academic context. */
    public function academicContext(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumItem::class, 'academic_selections')
            ->wherePivot('role', 'context')
            ->withTimestamps();
    }

    /** Subjects the student finds challenging, or the tutor supports. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumItem::class, 'academic_selections')
            ->wherePivot('role', 'subject')
            ->withTimestamps();
    }

    // -------------------------------------------------------------------- state

    public function getNameAttribute(?string $value): string
    {
        return $value ?: trim("{$this->first_name} {$this->last_name}");
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function isMinor(): bool
    {
        return $this->age() !== null && $this->age() < self::MINOR_AGE;
    }

    public function hasGuardianConsent(): bool
    {
        return $this->guardianConsent?->isApproved() ?? false;
    }

    /**
     * CON-03: a minor without approved consent may browse, but may not
     * raise requests, message anyone, or post publicly.
     */
    public function canParticipate(): bool
    {
        return ! $this->isMinor() || $this->hasGuardianConsent();
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isTutor(): bool
    {
        return $this->role === self::ROLE_TUTOR;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_MODERATOR, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isVerifiedTutor(): bool
    {
        return $this->isTutor() && $this->tutorProfile?->isApproved();
    }
}

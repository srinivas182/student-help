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
        'plan', 'plan_expires_at', 'free_for_life', 'monthly_request_quota', 'monthly_ai_quota',
        'notification_preferences', 'preferred_language_id',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

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
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_bypass_until' => 'datetime',
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Access\Models\Role::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function reviewerScopes(): HasMany
    {
        return $this->hasMany(\App\Domains\Access\Models\ReviewerScope::class);
    }

    /**
     * Super administrators hold every permission implicitly; everyone else holds
     * exactly what their assigned roles grant.
     */
    /**
     * Permissions that come with the account type itself, before any assigned
     * role. Without these an administrator with no role attached would have no
     * access at all, and a moderator would silently have everything.
     */
    public const BASE_PERMISSIONS = [
        self::ROLE_MODERATOR => [
            'moderation.queue', 'moderation.conversations',
            'moderation.groups', 'moderation.voice',
            'resources.review',
        ],
        self::ROLE_ADMIN => [
            'users.manage', 'users.suspend', 'staff.invite', 'tutors.verify',
            'curriculum.manage', 'announcements.manage', 'resources.review',
            'topics.manage', 'topics.generate', 'topics.publish', 'topics.review',
            'moderation.queue', 'moderation.conversations',
            'moderation.groups', 'moderation.voice',
            'assistant.configure', 'audit.view',
        ],
    ];

    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if (in_array($permission, self::BASE_PERMISSIONS[$this->role] ?? [], true)) {
            return true;
        }

        return $this->roles->contains(fn ($role) => $role->grants($permission));
    }

    /** A reviewer with no scope rows may review anything; scopes narrow them. */
    public function mayReview(?int $subjectId, ?int $languageId): bool
    {
        if (! $this->hasPermission('topics.review') && ! $this->hasPermission('topics.publish')) {
            return false;
        }

        $scopes = $this->reviewerScopes;

        if ($scopes->isEmpty()) {
            return true;
        }

        return $scopes->contains(function ($scope) use ($subjectId, $languageId) {
            $subjectOk = $scope->curriculum_item_id === null || $scope->curriculum_item_id === $subjectId;
            $languageOk = $scope->language_id === null || $scope->language_id === $languageId;

            return $subjectOk && $languageOk;
        });
    }

    public function preferredLanguage(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tutor\Models\Language::class, 'preferred_language_id');
    }

    public function studyGroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\StudyGroup\Models\StudyGroup::class, 'study_group_members')
            ->wherePivot('status', 'active')
            ->withPivot(['role', 'status', 'joined_at'])
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
    public function activityDays(): HasMany
    {
        return $this->hasMany(\App\Domains\Progress\Models\ActivityDay::class);
    }

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
    /**
     * Self-study needs no other person involved, so a learner waiting on
     * guardian approval can read, watch and test themselves straight away.
     * Only contact with other people waits for consent.
     */
    public function canSelfStudy(): bool
    {
        // Gate on being blocked rather than on being explicitly active: a
        // missing status should never quietly lock a learner out of studying.
        return ! in_array($this->status, ['suspended', 'banned'], true);
    }

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

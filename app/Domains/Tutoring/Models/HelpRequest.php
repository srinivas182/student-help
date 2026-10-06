<?php

namespace App\Domains\Tutoring\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Status model (SRS section 4.6):
 * open → assigned → resolved → closed, with escalated and cancelled branches.
 */
class HelpRequest extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'student_id', 'tutor_id', 'subject_id', 'topic', 'description', 'status',
        'assigned_at', 'escalated_at', 'resolved_at', 'closed_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumItem::class, 'subject_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(HelpRequestOffer::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HelpRequestAttachment::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    public function scopeOpenForMatching(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ESCALATED]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ESCALATED, self::STATUS_ASSIGNED], true);
    }

    /** MSG-08: conversations close with the request. */
    public function isConversationOpen(): bool
    {
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_RESOLVED], true);
    }
}

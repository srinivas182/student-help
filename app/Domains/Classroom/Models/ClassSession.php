<?php

namespace App\Domains\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassSession extends Model
{
    public const MODE_ONLINE = 'online';
    public const MODE_IN_PERSON = 'in_person';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'classroom_id', 'title', 'description', 'mode', 'meeting_url', 'location',
        'starts_at', 'duration_minutes', 'status', 'cancel_reason',
        'repeats', 'repeats_until', 'parent_session_id',
    ];

    /**
     * Mirrors the database defaults, so a freshly created session has a status
     * in memory too — without this, isJoinable() saw a null status and every
     * new session looked closed until it was reloaded.
     */
    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
        'mode' => self::MODE_ONLINE,
        'duration_minutes' => 60,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'repeats_until' => 'date',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_session_attendance')
            ->withPivot(['response', 'attended_at'])
            ->withTimestamps();
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('starts_at', '>=', now()->subHours(2))
            ->orderBy('starts_at');
    }

    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    /** The link appears shortly before the session, not days ahead. */
    public function isJoinable(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && now()->between($this->starts_at->copy()->subMinutes(15), $this->endsAt());
    }

    public function isPast(): bool
    {
        return $this->endsAt()->isPast();
    }

    public function whenLabel(): string
    {
        if ($this->starts_at->isToday()) {
            return 'Today at '.$this->starts_at->format('H:i');
        }

        if ($this->starts_at->isTomorrow()) {
            return 'Tomorrow at '.$this->starts_at->format('H:i');
        }

        return $this->starts_at->format('D j M, H:i');
    }
}

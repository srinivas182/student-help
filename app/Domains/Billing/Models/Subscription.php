<?php

namespace App\Domains\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'provider', 'provider_reference',
        'provider_token', 'amount_cents', 'starts_at', 'ends_at', 'cancelled_at',
    ];

    protected $hidden = ['provider_token'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** Cancelled subscriptions run to the end of the paid period, not instantly. */
    public function isUsable(): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            return $this->ends_at === null || $this->ends_at->isFuture();
        }

        return $this->status === self::STATUS_CANCELLED
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }
}

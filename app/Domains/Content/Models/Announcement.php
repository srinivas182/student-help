<?php

namespace App\Domains\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Targeted announcements (SRS: ANN-01 – ANN-05).
 *
 * Targeting is stored as roles plus curriculum item ids, so DX can post to
 * "all Grade 12s" or "everyone in the Engineering faculty" without a new field
 * every time the structure changes.
 */
class Announcement extends Model
{
    use HasFactory;

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_IMPORTANT = 'important';

    protected $fillable = [
        'author_id', 'title', 'body', 'priority', 'targeting', 'publish_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Does this announcement apply to the given user? */
    public function appliesTo(User $user): bool
    {
        $targeting = $this->targeting ?? [];

        $roles = $targeting['roles'] ?? [];

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return false;
        }

        $items = $targeting['curriculum_item_ids'] ?? [];

        if ($items === []) {
            return true;
        }

        $userItems = $user->academicContext()->pluck('curriculum_items.id')
            ->merge($user->subjects()->pluck('curriculum_items.id'));

        return $userItems->intersect($items)->isNotEmpty();
    }

    public function isScheduled(): bool
    {
        return $this->publish_at !== null && $this->publish_at->isFuture();
    }
}

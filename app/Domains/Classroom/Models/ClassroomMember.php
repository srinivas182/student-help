<?php

namespace App\Domains\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extends Pivot rather than Model so the relation can cast its columns: as a
 * plain Model, joined_at came back from the pivot as a raw string and every
 * date call on it threw, which is what broke the class page.
 */
class ClassroomMember extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $table = 'classroom_members';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';
    public const STATUS_LEFT = 'left';

    protected $fillable = ['classroom_id', 'user_id', 'status', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

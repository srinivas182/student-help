<?php

namespace App\Domains\Progress\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per day a student was active — cheap to write, cheap to count. */
class ActivityDay extends Model
{
    protected $table = 'activity_days';

    protected $fillable = ['user_id', 'day', 'actions'];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

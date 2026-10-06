<?php

namespace App\Domains\Tutoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `body` is what users see, with contact details masked (MSG-03).
 * `body_original` is moderator-only and every access is audit-logged (MSG-06).
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = ['help_request_id', 'sender_id', 'body', 'body_original', 'is_flagged', 'read_at'];

    protected $hidden = ['body_original'];

    protected function casts(): array
    {
        return ['is_flagged' => 'boolean', 'read_at' => 'datetime'];
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}

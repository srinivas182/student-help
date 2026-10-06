<?php

namespace App\Domains\Tutoring\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpRequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['help_request_id', 'path', 'original_name', 'mime_type', 'size'];

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }
}

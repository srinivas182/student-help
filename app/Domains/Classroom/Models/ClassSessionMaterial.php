<?php

namespace App\Domains\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Slides, notes or a worksheet attached to one session. */
class ClassSessionMaterial extends Model
{
    protected $fillable = [
        'class_session_id', 'uploaded_by', 'title',
        'path', 'external_url', 'mime_type', 'size',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLink(): bool
    {
        return $this->path === null && filled($this->external_url);
    }

    public function sizeLabel(): string
    {
        if ($this->size === 0) {
            return '';
        }

        return $this->size > 1048576
            ? round($this->size / 1048576, 1).' MB'
            : round($this->size / 1024).' KB';
    }
}

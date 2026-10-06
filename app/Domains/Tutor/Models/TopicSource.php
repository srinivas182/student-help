<?php

namespace App\Domains\Tutor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicSource extends Model
{
    use HasFactory;

    public const KIND_PDF = 'pdf';
    public const KIND_DOCUMENT = 'document';
    public const KIND_TEXT = 'text';
    public const KIND_VOICE = 'voice';
    public const KIND_LINK = 'link';

    protected $fillable = [
        'topic_id', 'uploaded_by', 'kind', 'title', 'path', 'external_url',
        'extracted_text', 'size', 'rights_declared', 'extraction_status',
    ];

    protected function casts(): array
    {
        return ['rights_declared' => 'boolean'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

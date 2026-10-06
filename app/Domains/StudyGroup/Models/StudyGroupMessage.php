<?php

namespace App\Domains\StudyGroup\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyGroupMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_group_id', 'user_id', 'body', 'body_original', 'is_flagged', 'is_removed',
    ];

    protected $hidden = ['body_original'];

    protected function casts(): array
    {
        return ['is_flagged' => 'boolean', 'is_removed' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class, 'study_group_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visibleBody(): string
    {
        return $this->is_removed ? '[removed by a moderator]' : $this->body;
    }
}

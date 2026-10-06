<?php

namespace App\Domains\Assistant\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'help_request_id', 'curriculum_item_id', 'question', 'answer',
        'provider', 'model', 'input_tokens', 'output_tokens', 'cost_usd',
        'refused', 'escalated', 'helpful',
    ];

    protected function casts(): array
    {
        return ['refused' => 'boolean', 'escalated' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumItem::class, 'curriculum_item_id');
    }
}

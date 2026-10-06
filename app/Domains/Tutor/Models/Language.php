<?php

namespace App\Domains\Tutor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'native_name', 'is_active', 'tts_supported', 'tts_voice', 'position',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tts_supported' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    public function isEnglish(): bool
    {
        return $this->code === 'en';
    }
}

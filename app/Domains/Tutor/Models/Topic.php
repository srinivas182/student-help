<?php

namespace App\Domains\Tutor\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_item_id', 'created_by', 'title', 'slug', 'summary',
        'objectives', 'estimated_minutes', 'position', 'is_published',
    ];

    protected function casts(): array
    {
        return ['objectives' => 'array', 'is_published' => 'boolean'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumItem::class, 'curriculum_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(TopicSource::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TopicVersion::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function versionFor(Language $language): ?TopicVersion
    {
        return $this->versions()
            ->where('language_id', $language->id)
            ->where('status', TopicVersion::STATUS_PUBLISHED)
            ->first();
    }

    /** Falls back to English so a student never hits an empty screen. */
    public function bestVersionFor(?Language $preferred): ?TopicVersion
    {
        if ($preferred && $version = $this->versionFor($preferred)) {
            return $version;
        }

        return $this->versions()
            ->where('status', TopicVersion::STATUS_PUBLISHED)
            ->whereHas('language', fn ($q) => $q->where('code', 'en'))
            ->first();
    }
}

<?php

namespace App\Domains\Curriculum\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the academic tree. The `type` column gives the node its meaning:
 * pathway, institution_type, track, qualification, level, faculty or subject.
 */
class CurriculumItem extends Model
{
    use HasFactory;

    public const TYPE_PATHWAY = 'pathway';
    public const TYPE_INSTITUTION_TYPE = 'institution_type';
    public const TYPE_TRACK = 'track';
    public const TYPE_QUALIFICATION = 'qualification';
    public const TYPE_LEVEL = 'level';
    public const TYPE_FACULTY = 'faculty';
    public const TYPE_SUBJECT = 'subject';

    protected $fillable = [
        'parent_id', 'type', 'name', 'slug', 'code', 'icon', 'description', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Ancestors from the root down to this item's parent. */
    /**
     * Subjects are read on every onboarding step, subject picker and resource
     * filter, and change a few times a year. Worth caching.
     */
    public static function cachedSubjects(): \Illuminate\Support\Collection
    {
        // Plain arrays rather than hydrated models: smaller in the cache, and
        // immune to the serialisation problems models have across deploys.
        $rows = cache()->remember(
            \App\Support\CacheKeys::curriculumSubjects(),
            \App\Support\CacheKeys::TTL_CURRICULUM,
            fn () => static::ofType(static::TYPE_SUBJECT)->active()
                ->get(['id', 'parent_id', 'name', 'code'])
                ->toArray(),
        );

        return collect($rows);
    }

    public static function cachedChildren(?int $parentId): \Illuminate\Support\Collection
    {
        $rows = cache()->remember(
            \App\Support\CacheKeys::curriculumChildren($parentId),
            \App\Support\CacheKeys::TTL_CURRICULUM,
            fn () => static::where('parent_id', $parentId)->active()
                ->orderBy('position')
                ->get(['id', 'parent_id', 'type', 'name', 'code', 'description'])
                ->toArray(),
        );

        return collect($rows);
    }

    protected static function booted(): void
    {
        // Any edit clears the cache, so admin changes show immediately
        static::saved(fn () => \App\Support\CacheKeys::forgetCurriculum());
        static::deleted(fn () => \App\Support\CacheKeys::forgetCurriculum());
    }

    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;

        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }
}

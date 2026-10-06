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

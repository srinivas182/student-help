<?php

namespace App\Domains\Content\Models;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Resource extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'uploaded_by', 'title', 'description', 'resource_type', 'path', 'external_url',
        'mime_type', 'size', 'status', 'rights_declared', 'approved_by', 'approved_at',
        'views', 'downloads',
    ];

    protected function casts(): array
    {
        return ['rights_declared' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function curriculumItems(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumItem::class, 'resource_curriculum_item');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** RES-03: a student sees only material tagged to their own subjects. */
    public function scopeForStudent(Builder $query, User $student): Builder
    {
        $ids = $student->subjects()->pluck('curriculum_items.id')
            ->merge($student->academicContext()->pluck('curriculum_items.id'));

        return $query->published()
            ->whereHas('curriculumItems', fn ($q) => $q->whereIn('curriculum_items.id', $ids));
    }

    public function isDownloadable(): bool
    {
        return $this->path !== null;
    }
}

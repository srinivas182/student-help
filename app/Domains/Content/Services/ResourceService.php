<?php

namespace App\Domains\Content\Services;

use App\Domains\Content\Models\Resource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Study notes, past papers and solutions (SRS: RES-01 – RES-08).
 *
 * Teacher uploads are held for approval; administrator uploads publish straight
 * away. Everything is tagged to the curriculum so students only ever see
 * material for their own grade and subjects.
 */
class ResourceService
{
    public const TYPES = [
        'notes' => 'Study notes',
        'course_material' => 'Course material',
        'past_paper' => 'Past paper',
        'solution' => 'Past paper solution',
        'video' => 'Video link',
        'other' => 'Other',
    ];

    public function create(User $uploader, array $data, ?UploadedFile $file = null): Resource
    {
        $path = $file?->store('resources/'.$uploader->id, 'local');

        $resource = Resource::create([
            'uploaded_by' => $uploader->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'resource_type' => $data['resource_type'],
            'path' => $path,
            'external_url' => $data['external_url'] ?? null,
            'mime_type' => $file?->getClientMimeType(),
            'size' => $file?->getSize(),
            'rights_declared' => true,
            // RES-02: only administrators publish without review.
            'status' => $uploader->isStaff() ? Resource::STATUS_PUBLISHED : Resource::STATUS_PENDING,
            'approved_by' => $uploader->isStaff() ? $uploader->id : null,
            'approved_at' => $uploader->isStaff() ? now() : null,
        ]);

        $resource->curriculumItems()->sync($data['curriculum_item_ids']);

        audit('resource.created', $resource, [
            'status' => $resource->status,
            'type' => $resource->resource_type,
        ]);

        return $resource;
    }

    public function approve(Resource $resource, User $reviewer): void
    {
        $resource->update([
            'status' => Resource::STATUS_PUBLISHED,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);

        audit('resource.approved', $resource, ['reviewer_id' => $reviewer->id]);
    }

    public function reject(Resource $resource, User $reviewer, string $reason): void
    {
        $resource->update([
            'status' => Resource::STATUS_REJECTED,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);

        audit('resource.rejected', $resource, ['reviewer_id' => $reviewer->id, 'reason' => $reason]);
    }

    public function unpublish(Resource $resource, User $actor, string $reason): void
    {
        $resource->update(['status' => Resource::STATUS_UNPUBLISHED]);

        audit('resource.unpublished', $resource, ['actor_id' => $actor->id, 'reason' => $reason]);
    }

    public function delete(Resource $resource): void
    {
        if ($resource->path) {
            Storage::disk('local')->delete($resource->path);
        }

        audit('resource.deleted', $resource);

        $resource->delete();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Content\Models\Resource;
use App\Domains\Content\Services\ResourceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** RES-02, RES-07: approval queue and takedown. */
class ResourceReviewController extends Controller
{
    public function __construct(private readonly ResourceService $resources)
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: Resource::STATUS_PENDING;

        return Inertia::render('Admin/Resources', [
            'resources' => Resource::where('status', $status)
                ->with(['uploader:id,first_name,last_name', 'curriculumItems:id,name'])
                ->oldest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Resource $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'type' => ResourceService::TYPES[$r->resource_type] ?? $r->resource_type,
                    'uploader' => $r->uploader?->name,
                    'subjects' => $r->curriculumItems->pluck('name'),
                    'sizeKb' => $r->size ? (int) round($r->size / 1024) : null,
                    'hasFile' => $r->path !== null,
                    'externalUrl' => $r->external_url,
                    'addedAt' => $r->created_at?->diffForHumans(),
                ]),
            'filters' => ['status' => $status],
            'counts' => [
                'pending' => Resource::where('status', Resource::STATUS_PENDING)->count(),
                'published' => Resource::where('status', Resource::STATUS_PUBLISHED)->count(),
                'rejected' => Resource::where('status', Resource::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    public function approve(Request $request, Resource $resource): RedirectResponse
    {
        $this->resources->approve($resource, $request->user());

        return back()->with('success', 'Published to students in those subjects.');
    }

    public function reject(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $this->resources->reject($resource, $request->user(), $validated['reason']);

        return back()->with('success', 'Rejected and the uploader has been told why.');
    }

    public function unpublish(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->resources->unpublish($resource, $request->user(), $validated['reason']);

        return back()->with('success', 'Removed from the library.');
    }
}

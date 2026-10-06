<?php

namespace App\Http\Controllers;

use App\Domains\Content\Models\Resource;
use App\Domains\Content\Services\ResourceService;
use App\Domains\Curriculum\Models\CurriculumItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceController extends Controller
{
    public function __construct(private readonly ResourceService $resources)
    {
    }

    /** RES-03, RES-04: the student library, filtered to their own curriculum. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = $user->isStudent()
            ? Resource::forStudent($user)
            : Resource::published();

        $resources = $query
            ->when($request->string('search')->toString(), fn ($q, $term) => $q->where(
                fn ($inner) => $inner->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%"),
            ))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('resource_type', $type))
            ->when($request->integer('subject'), fn ($q, $subject) => $q
                ->whereHas('curriculumItems', fn ($c) => $c->where('curriculum_items.id', $subject)))
            ->with(['uploader:id,first_name,last_name', 'curriculumItems:id,name'])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Resource $resource) => $this->present($resource));

        return Inertia::render('Resources/Index', [
            'resources' => $resources,
            'filters' => $request->only('search', 'type', 'subject'),
            'types' => ResourceService::TYPES,
            'subjects' => $user->subjects()->get(['curriculum_items.id', 'name']),
            'canUpload' => $user->isVerifiedTutor() || $user->isStaff(),
        ]);
    }

    public function show(Request $request, Resource $resource): Response
    {
        abort_unless($this->canView($request, $resource), 403);

        $resource->increment('views');
        $resource->load(['uploader:id,first_name,last_name', 'curriculumItems:id,name']);

        return Inertia::render('Resources/Show', [
            'resource' => $this->present($resource) + [
                'description' => $resource->description,
                'mimeType' => $resource->mime_type,
                'externalUrl' => $resource->external_url,
            ],
        ]);
    }

    /** RES-05: streamed so files are never publicly linked. */
    public function download(Request $request, Resource $resource): StreamedResponse
    {
        abort_unless($this->canView($request, $resource), 403);
        abort_unless($resource->path && Storage::disk('local')->exists($resource->path), 404);

        $resource->increment('downloads');

        return Storage::disk('local')->response($resource->path, $resource->title);
    }

    /** RES-01: teachers and administrators share material. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isVerifiedTutor() || $user->isStaff(), 403);

        $maxMb = (int) setting('max_attachment_mb', 10);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'resource_type' => ['required', Rule::in(array_keys(ResourceService::TYPES))],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,mp3,m4a', 'max:'.($maxMb * 1024)],
            'external_url' => ['nullable', 'url', 'max:500', 'required_without:file'],
            'curriculum_item_ids' => ['required', 'array', 'min:1'],
            'curriculum_item_ids.*' => ['integer', 'exists:curriculum_items,id'],
            // Copyright declaration — DX carries the liability otherwise.
            'rights_declared' => ['accepted'],
        ], [
            'external_url.required_without' => 'Upload a file or provide a link.',
            'rights_declared.accepted' => 'Please confirm you have the right to share this material.',
            'curriculum_item_ids.required' => 'Tag at least one subject so the right students see it.',
        ]);

        $resource = $this->resources->create($user, $validated, $request->file('file'));

        return redirect()->route('resources.mine')->with(
            'success',
            $resource->status === Resource::STATUS_PUBLISHED
                ? 'Published. Students in those subjects can see it now.'
                : 'Uploaded. A DX administrator will review it shortly.',
        );
    }

    /** A teacher's own uploads, including anything pending or rejected. */
    public function mine(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isTutor() || $user->isStaff(), 403);

        return Inertia::render('Resources/Mine', [
            'resources' => Resource::where('uploaded_by', $user->id)
                ->with('curriculumItems:id,name')
                ->latest()
                ->paginate(15)
                ->through(fn (Resource $resource) => $this->present($resource)),
            'types' => ResourceService::TYPES,
            'subjects' => $user->subjects()->get(['curriculum_items.id', 'name']),
            'maxMb' => (int) setting('max_attachment_mb', 10),
        ]);
    }

    private function canView(Request $request, Resource $resource): bool
    {
        $user = $request->user();

        if ($user->isStaff() || $resource->uploaded_by === $user->id) {
            return true;
        }

        if ($resource->status !== Resource::STATUS_PUBLISHED) {
            return false;
        }

        if (! $user->isStudent()) {
            return true;
        }

        $ids = $user->subjects()->pluck('curriculum_items.id')
            ->merge($user->academicContext()->pluck('curriculum_items.id'));

        return $resource->curriculumItems()->whereIn('curriculum_items.id', $ids)->exists();
    }

    private function present(Resource $resource): array
    {
        return [
            'id' => $resource->id,
            'title' => $resource->title,
            'type' => $resource->resource_type,
            'typeLabel' => ResourceService::TYPES[$resource->resource_type] ?? $resource->resource_type,
            'status' => $resource->status,
            'uploader' => $resource->uploader?->name,
            'subjects' => $resource->curriculumItems->pluck('name'),
            'sizeKb' => $resource->size ? (int) round($resource->size / 1024) : null,
            'isDownloadable' => $resource->isDownloadable(),
            'views' => $resource->views,
            'downloads' => $resource->downloads,
            'addedAt' => $resource->created_at?->diffForHumans(),
        ];
    }
}

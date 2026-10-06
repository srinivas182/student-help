<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Curriculum editor (SRS: CUR-01 – CUR-05).
 *
 * This is what lets DX correct the assumed curriculum themselves, and keep it
 * current every year, without a release.
 */
class CurriculumController extends Controller
{
    private const CHILD_TYPE = [
        CurriculumItem::TYPE_PATHWAY => CurriculumItem::TYPE_INSTITUTION_TYPE,
        CurriculumItem::TYPE_INSTITUTION_TYPE => CurriculumItem::TYPE_LEVEL,
        CurriculumItem::TYPE_TRACK => CurriculumItem::TYPE_QUALIFICATION,
        CurriculumItem::TYPE_QUALIFICATION => CurriculumItem::TYPE_LEVEL,
        CurriculumItem::TYPE_LEVEL => CurriculumItem::TYPE_SUBJECT,
        CurriculumItem::TYPE_FACULTY => CurriculumItem::TYPE_SUBJECT,
    ];

    public function index(Request $request): Response
    {
        $parentId = $request->integer('parent');
        $parent = $parentId ? CurriculumItem::find($parentId) : null;

        $items = CurriculumItem::query()
            ->when($parent, fn ($q) => $q->where('parent_id', $parent->id))
            ->when(! $parent, fn ($q) => $q->whereNull('parent_id'))
            ->withCount('children')
            ->orderBy('position')
            ->get()
            ->map(fn (CurriculumItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'code' => $item->code,
                'description' => $item->description,
                'children' => $item->children_count,
                'isActive' => $item->is_active,
                'position' => $item->position,
            ]);

        return Inertia::render('Admin/Curriculum/Index', [
            'items' => $items,
            'parent' => $parent ? [
                'id' => $parent->id,
                'name' => $parent->name,
                'type' => $parent->type,
                'parentId' => $parent->parent_id,
            ] : null,
            'breadcrumb' => $parent
                ? collect($parent->ancestors())->push($parent)
                    ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()
                : [],
            'childType' => $parent
                ? (self::CHILD_TYPE[$parent->type] ?? CurriculumItem::TYPE_SUBJECT)
                : CurriculumItem::TYPE_PATHWAY,
            'typeOptions' => [
                CurriculumItem::TYPE_PATHWAY, CurriculumItem::TYPE_INSTITUTION_TYPE,
                CurriculumItem::TYPE_TRACK, CurriculumItem::TYPE_QUALIFICATION,
                CurriculumItem::TYPE_LEVEL, CurriculumItem::TYPE_FACULTY,
                CurriculumItem::TYPE_SUBJECT,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateItem($request);

        $parent = $validated['parent_id'] ? CurriculumItem::find($validated['parent_id']) : null;

        CurriculumItem::create([
            ...$validated,
            'slug' => Str::slug(($parent?->slug ? $parent->slug.'-' : '').$validated['name']),
            'position' => (int) CurriculumItem::where('parent_id', $validated['parent_id'])->max('position') + 1,
            'is_active' => true,
        ]);

        audit('curriculum.created', $parent, ['name' => $validated['name'], 'type' => $validated['type']]);

        return back()->with('success', "{$validated['name']} added.");
    }

    public function update(Request $request, CurriculumItem $curriculumItem): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $curriculumItem->update($validated);

        audit('curriculum.updated', $curriculumItem, $validated);

        return back()->with('success', 'Saved.');
    }

    /**
     * CUR-02: deactivating hides an item from new selections but keeps it on
     * existing student records, so history is never rewritten.
     */
    public function toggle(CurriculumItem $curriculumItem): RedirectResponse
    {
        $curriculumItem->update(['is_active' => ! $curriculumItem->is_active]);

        audit('curriculum.toggled', $curriculumItem, ['is_active' => $curriculumItem->is_active]);

        return back()->with('success', $curriculumItem->is_active
            ? "{$curriculumItem->name} is visible to students again."
            : "{$curriculumItem->name} is hidden from new selections. Existing students keep it.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:curriculum_items,id'],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            CurriculumItem::whereKey($id)->update(['position' => $position]);
        }

        return back();
    }

    /** CUR-04: bulk import with row-level feedback. */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['required', 'integer', 'exists:curriculum_items,id'],
            'names' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in([
                CurriculumItem::TYPE_SUBJECT, CurriculumItem::TYPE_LEVEL,
                CurriculumItem::TYPE_FACULTY, CurriculumItem::TYPE_QUALIFICATION,
            ])],
        ]);

        $parent = CurriculumItem::findOrFail($validated['parent_id']);
        $position = (int) CurriculumItem::where('parent_id', $parent->id)->max('position');
        $added = 0;
        $skipped = 0;

        foreach (preg_split('/\r\n|\r|\n/', $validated['names']) as $line) {
            $name = trim($line);

            if ($name === '') {
                continue;
            }

            $exists = CurriculumItem::where('parent_id', $parent->id)->where('name', $name)->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            CurriculumItem::create([
                'parent_id' => $parent->id,
                'type' => $validated['type'],
                'name' => $name,
                'slug' => Str::slug($parent->slug.'-'.$name),
                'position' => ++$position,
                'is_active' => true,
            ]);

            $added++;
        }

        audit('curriculum.imported', $parent, ['added' => $added, 'skipped' => $skipped]);

        return back()->with('success', "Added {$added} item(s)".($skipped ? ", skipped {$skipped} duplicate(s)." : '.'));
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:curriculum_items,id'],
            'type' => ['required', Rule::in([
                CurriculumItem::TYPE_PATHWAY, CurriculumItem::TYPE_INSTITUTION_TYPE,
                CurriculumItem::TYPE_TRACK, CurriculumItem::TYPE_QUALIFICATION,
                CurriculumItem::TYPE_LEVEL, CurriculumItem::TYPE_FACULTY,
                CurriculumItem::TYPE_SUBJECT,
            ])],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }
}

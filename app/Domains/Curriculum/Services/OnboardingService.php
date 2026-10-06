<?php

namespace App\Domains\Curriculum\Services;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Drives the onboarding wizard straight from the curriculum tree (SRS: ONB-01 – ONB-06).
 *
 * Because every step is a node in the tree, the School, College and University
 * journeys all work without pathway-specific code, and DX can change the steps
 * from the admin console without a release.
 */
class OnboardingService
{
    /** Copy shown above the options at each step, keyed by the type being chosen. */
    private const PROMPTS = [
        CurriculumItem::TYPE_PATHWAY => ['Where are you studying?', 'Choose the pathway that matches your current studies.'],
        CurriculumItem::TYPE_INSTITUTION_TYPE => ['What type of institution?', 'This helps us match you to the right curriculum.'],
        CurriculumItem::TYPE_TRACK => ['Which programme type?', 'Select the track you are enrolled in.'],
        CurriculumItem::TYPE_QUALIFICATION => ['Select your qualification', 'Choose the qualification you are working towards.'],
        CurriculumItem::TYPE_LEVEL => ['Select your level', 'Choose the grade or year you are currently in.'],
        CurriculumItem::TYPE_FACULTY => ['Select your faculty', 'We will tailor your resources and tutors to it.'],
    ];

    /** The nodes the user has already chosen, in order from pathway downwards. */
    public function context(User $user): Collection
    {
        return $user->academicContext()->orderBy('position')->get();
    }

    /** The next set of options, or null when the user has reached subject selection. */
    public function nextStep(User $user): ?array
    {
        $context = $this->context($user);
        $current = $context->last();

        $options = $current
            ? $current->children()->active()->get()
            : CurriculumItem::active()->ofType(CurriculumItem::TYPE_PATHWAY)->orderBy('position')->get();

        if ($options->isEmpty() || $options->first()->type === CurriculumItem::TYPE_SUBJECT) {
            return null;
        }

        $type = $options->first()->type;
        [$title, $subtitle] = self::PROMPTS[$type] ?? ['Select an option', 'Choose the option that applies to you.'];

        return [
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'options' => $options->map(fn (CurriculumItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'icon' => $item->icon,
            ])->all(),
            'breadcrumb' => $context->pluck('name')->all(),
            'step' => $context->count() + 1,
        ];
    }

    /** Subjects available at the user's current position in the tree. */
    public function availableSubjects(User $user): Collection
    {
        $current = $this->context($user)->last();

        if (! $current) {
            return collect();
        }

        return $current->children()->active()->ofType(CurriculumItem::TYPE_SUBJECT)->get();
    }

    /** ONB-05: only accept a node that is genuinely the next valid option. */
    public function isValidChoice(User $user, CurriculumItem $choice): bool
    {
        $current = $this->context($user)->last();

        return $current
            ? $choice->parent_id === $current->id
            : $choice->type === CurriculumItem::TYPE_PATHWAY;
    }

    public function addContext(User $user, CurriculumItem $choice): void
    {
        $user->academicContext()->syncWithoutDetaching([$choice->id => ['role' => 'context']]);
    }

    /** Remove the last choice so the user can step back (ONB-10). */
    public function stepBack(User $user): void
    {
        $last = $this->context($user)->last();

        if ($last) {
            $user->academicContext()->detach($last->id);
        }
    }

    public function reset(User $user): void
    {
        $user->academicContext()->detach();
        $user->subjects()->detach();
        $user->update(['onboarding_completed_at' => null]);
    }

    /** @param  array<int>  $subjectIds */
    public function completeWithSubjects(User $user, array $subjectIds): void
    {
        $valid = $this->availableSubjects($user)->pluck('id')->intersect($subjectIds);

        $user->subjects()->sync($valid->mapWithKeys(fn ($id) => [$id => ['role' => 'subject']]));
        $user->update(['onboarding_completed_at' => now()]);
    }
}

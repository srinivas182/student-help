<?php

namespace App\Http\Controllers\Onboarding;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Services\OnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $onboarding)
    {
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        $step = $this->onboarding->nextStep($user);

        if ($step === null) {
            return redirect()->route('onboarding.subjects');
        }

        return Inertia::render('Onboarding/Step', [
            'step' => $step,
            'canGoBack' => $this->onboarding->context($user)->isNotEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curriculum_item_id' => ['required', 'integer', 'exists:curriculum_items,id'],
        ]);

        $user = $request->user();
        $choice = CurriculumItem::findOrFail($validated['curriculum_item_id']);

        if (! $this->onboarding->isValidChoice($user, $choice)) {
            return back()->withErrors(['curriculum_item_id' => 'That option is not available at this step.']);
        }

        $this->onboarding->addContext($user, $choice);

        return redirect()->route('onboarding.show');
    }

    public function back(Request $request): RedirectResponse
    {
        $this->onboarding->stepBack($request->user());

        return redirect()->route('onboarding.show');
    }

    public function subjects(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $subjects = $this->onboarding->availableSubjects($user);

        if ($subjects->isEmpty()) {
            return redirect()->route('onboarding.show');
        }

        return Inertia::render('Onboarding/Subjects', [
            'subjects' => $subjects->map(fn (CurriculumItem $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ])->values(),
            'breadcrumb' => $this->onboarding->context($user)->pluck('name'),
            'selected' => $user->subjects()->pluck('curriculum_items.id'),
            'isTutor' => $user->isTutor(),
        ]);
    }

    public function storeSubjects(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'exists:curriculum_items,id'],
        ], [
            'subject_ids.required' => 'Choose at least one subject so we can tailor the platform to you.',
        ]);

        $this->onboarding->completeWithSubjects($request->user(), $validated['subject_ids']);

        return redirect()->route('dashboard')->with('success', 'Your profile is set up. Welcome to DX Student Help.');
    }

    public function restart(Request $request): RedirectResponse
    {
        $this->onboarding->reset($request->user());

        return redirect()->route('onboarding.show');
    }
}

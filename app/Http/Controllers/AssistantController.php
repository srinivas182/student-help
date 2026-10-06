<?php

namespace App\Http\Controllers;

use App\Domains\Assistant\Models\AiAnswer;
use App\Domains\Assistant\Services\AssistantService;
use App\Domains\Assistant\Services\QuotaService;
use App\Domains\Tutoring\Models\HelpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistant,
        private readonly QuotaService $quota,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Assistant/Index', [
            'quota' => $this->quota->summary($user),
            'history' => AiAnswer::where('user_id', $user->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (AiAnswer $a) => [
                    'id' => $a->id,
                    'question' => $a->question,
                    'answer' => $a->answer,
                    'refused' => $a->refused,
                    'helpful' => $a->helpful,
                    'askedAt' => $a->created_at?->diffForHumans(),
                ]),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:10', 'max:1500'],
            'help_request_id' => ['nullable', 'integer', 'exists:help_requests,id'],
        ]);

        $helpRequest = isset($validated['help_request_id'])
            ? HelpRequest::findOrFail($validated['help_request_id'])
            : null;

        if ($helpRequest) {
            abort_unless($helpRequest->student_id === $request->user()->id, 403);
            abort_unless($this->assistant->isOfferedFor($helpRequest), 422,
                'A tutor still has time to answer this one.');
        }

        $this->assistant->ask($request->user(), $validated['question'], $helpRequest);

        return back()->with('success', null);
    }

    /** The student decides the AI was not enough and wants a person. */
    public function escalate(Request $request, AiAnswer $answer): RedirectResponse
    {
        abort_unless($answer->user_id === $request->user()->id, 403);

        $answer->update(['escalated' => true]);

        audit('ai.escalated_to_human', $answer);

        return redirect()->route('requests.create')
            ->with('success', 'Let us get a tutor to help instead. Ask your question here.');
    }

    public function feedback(Request $request, AiAnswer $answer): RedirectResponse
    {
        abort_unless($answer->user_id === $request->user()->id, 403);

        $validated = $request->validate(['helpful' => ['required', 'boolean']]);

        $answer->update(['helpful' => (int) $validated['helpful']]);

        return back();
    }
}

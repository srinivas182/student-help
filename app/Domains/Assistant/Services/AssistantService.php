<?php

namespace App\Domains\Assistant\Services;

use App\Domains\Assistant\Models\AiAnswer;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssistantService
{
    /**
     * Phrases that mean "do my assessment for me" rather than "help me learn".
     * The assistant redirects these instead of complying — the same academic
     * honesty line tutors are held to.
     */
    private const ASSESSMENT_PATTERNS = [
        '/\b(do|complete|finish|write|answer)\s+(my|this|the)\s+(homework|assignment|test|exam|essay|project|task)\b/i',
        '/\bgive me the (answers?|solutions?)\b/i',
        '/\bwhat is the answer to question\b/i',
        '/\bwrite (my|an?) essay\b/i',
    ];

    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly QuotaService $quota,
        private readonly AssistantProvider $provider,
    ) {
    }

    public function ask(User $student, string $question, ?HelpRequest $request = null): AiAnswer
    {
        if (! $student->canParticipate()) {
            throw ValidationException::withMessages([
                'question' => 'A parent or guardian needs to approve your account first.',
            ]);
        }

        $check = $this->quota->check($student);

        if (! $check['allowed']) {
            throw ValidationException::withMessages(['question' => $check['message']]);
        }

        // Refusals are recorded but never counted against the student's quota.
        if ($this->looksLikeAssessmentRequest($question)) {
            return $this->recordRefusal($student, $question, $request);
        }

        $context = $this->contextFor($student, $request);
        $result = $this->provider->ask($this->systemPrompt($context), $question);

        $answer = AiAnswer::create([
            'user_id' => $student->id,
            'help_request_id' => $request?->id,
            'curriculum_item_id' => $request?->subject_id,
            'question' => $question,
            'answer' => $result['text'],
            'provider' => $this->provider->name(),
            'model' => $this->provider->model(),
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'cost_usd' => $result['cost_usd'],
        ]);

        audit('ai.answered', $answer, [
            'cost_usd' => $result['cost_usd'],
            'request_id' => $request?->id,
        ]);

        return $answer;
    }

    /** REQ-06 companion: offer the AI only once a human has had their chance. */
    public function isOfferedFor(HelpRequest $request): bool
    {
        if (! $this->settings->isEnabled()) {
            return false;
        }

        if ($this->settings->isDirectAskAllowed()) {
            return true;
        }

        return in_array($request->status, [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED], true)
            && $request->created_at->lte(now()->subHours($this->settings->fallbackAfterHours()));
    }

    public function looksLikeAssessmentRequest(string $question): bool
    {
        foreach (self::ASSESSMENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }

    private function recordRefusal(User $student, string $question, ?HelpRequest $request): AiAnswer
    {
        $answer = AiAnswer::create([
            'user_id' => $student->id,
            'help_request_id' => $request?->id,
            'curriculum_item_id' => $request?->subject_id,
            'question' => $question,
            'answer' => "I can't complete an assessment for you — that would not be fair to you or to your classmates, and you would not learn the method.\n\nWhat I can do is explain how this type of question works and walk through a similar example, so you can do yours yourself. Try asking me \"how do I approach this kind of question?\" instead, or ask a tutor who can work through it with you.",
            'provider' => 'policy',
            'model' => 'policy',
            'refused' => true,
        ]);

        audit('ai.refused_assessment', $answer);

        return $answer;
    }

    private function contextFor(User $student, ?HelpRequest $request): array
    {
        return [
            'level' => $student->academicContext()->pluck('name')->implode(' · '),
            'subject' => $request?->subject?->name ?? $student->subjects()->pluck('name')->implode(', '),
            'country' => 'South Africa',
        ];
    }

    private function systemPrompt(array $context): string
    {
        return <<<PROMPT
        You are the study assistant on DX Student Help, a South African tutoring platform.
        You are talking to a student at this level: {$context['level']}.
        Subject context: {$context['subject']}.

        How to answer:
        - Explain the method so the student can solve it themselves. Never just give a final answer.
        - Work through a similar example rather than their exact assessment question.
        - Pitch your language at their academic level. Use South African curriculum terms.
        - Keep it short enough to read on a phone, with clear steps.
        - Use metric units and South African context in examples.

        Hard rules:
        - You are an AI, not a person. Never imply otherwise.
        - Never complete homework, assignments, tests or essays. Redirect to method instead.
        - If the question is about something personal, emotional, or about the student's
          safety or wellbeing, do not advise. Tell them a human tutor or a trusted adult
          is the right person, and suggest they ask a tutor on the platform.
        - If you are unsure, say so and recommend asking a verified tutor.
        PROMPT;
    }
}

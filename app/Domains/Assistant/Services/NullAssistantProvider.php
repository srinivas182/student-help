<?php

namespace App\Domains\Assistant\Services;

/**
 * Used when no provider is configured. The assistant simply reports that it is
 * unavailable rather than pretending to answer — a wrong answer to a learner is
 * worse than no answer.
 */
class NullAssistantProvider implements AssistantProvider
{
    public function ask(string $systemPrompt, string $question): array
    {
        return [
            'text' => 'The study assistant is not available right now. Ask a tutor instead — they are free and usually reply within a few hours.',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0.0,
        ];
    }

    public function name(): string
    {
        return 'none';
    }

    public function model(): string
    {
        return 'none';
    }
}

<?php

namespace App\Domains\Assistant\Services;

/**
 * @phpstan-type Answer array{text: string, input_tokens: int, output_tokens: int, cost_usd: float}
 */
interface AssistantProvider
{
    /** @return array{text: string, input_tokens: int, output_tokens: int, cost_usd: float} */
    public function ask(string $systemPrompt, string $question): array;

    public function name(): string;

    public function model(): string;
}

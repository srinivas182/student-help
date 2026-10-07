<?php

namespace App\Domains\Tutor\Services;

use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;

/**
 * What a generation will cost, before anyone commits to it.
 *
 * Rough but honest: token counts are estimated from the source word count and
 * the expected lesson shape, and priced against the rate DX configured for the
 * model they chose. Shown per language, with the month's budget alongside.
 */
class GenerationEstimator
{
    /** Roughly 0.75 words per token for English prose. */
    private const WORDS_PER_TOKEN = 0.75;

    /** A full lesson, notes, flashcards and 20+ questions. */
    private const OUTPUT_TOKENS_PER_LANGUAGE = 8000;

    public function estimate(Topic $topic, array $languageIds): array
    {
        $words = $topic->sources()
            ->whereNotNull('extracted_text')
            ->get()
            ->sum(fn ($source) => str_word_count($source->extracted_text));

        $inputTokens = (int) ceil($words / self::WORDS_PER_TOKEN) + 1200; // plus the prompt
        $languages = Language::whereIn('id', $languageIds)->get();

        $inputRate = (float) setting('ai_tutor_input_cost_per_million', 3.0);
        $outputRate = (float) setting('ai_tutor_output_cost_per_million', 15.0);

        $perLanguage = $languages->map(function (Language $language) use ($inputTokens, $inputRate, $outputRate) {
            // Non-English versions cost a little more: the model works harder.
            $output = (int) (self::OUTPUT_TOKENS_PER_LANGUAGE * ($language->code === 'en' ? 1 : 1.15));

            $cost = ($inputTokens / 1_000_000 * $inputRate) + ($output / 1_000_000 * $outputRate);

            return [
                'language' => $language->native_name,
                'code' => $language->code,
                'inputTokens' => $inputTokens,
                'outputTokens' => $output,
                'costUsd' => round($cost, 4),
            ];
        });

        $total = round($perLanguage->sum('costUsd'), 4);

        return [
            'sourceWords' => $words,
            'languages' => $perLanguage->values(),
            'totalUsd' => $total,
            'totalZar' => round($total * (float) setting('usd_to_zar', 18.0), 2),
            'requiresOtp' => $this->requiresOtp($total, count($languageIds)),
        ];
    }

    /** Routine work stays fast; the expensive actions get a second factor. */
    public function requiresOtp(float $costUsd, int $languageCount): bool
    {
        if (! (bool) setting('ai_tutor_otp_enabled', true)) {
            return false;
        }

        return $costUsd >= (float) setting('ai_tutor_otp_cost_threshold', 2.0)
            || $languageCount >= (int) setting('ai_tutor_otp_language_threshold', 5);
    }
}

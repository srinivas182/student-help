<?php

namespace App\Domains\Tutor\Services;

use App\Domains\Assistant\Services\AssistantProvider;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use Illuminate\Support\Facades\DB;

/**
 * Generates a lesson for one topic in one language.
 *
 * Teaching decisions baked into the prompt, from what actually works:
 *  - short segments the learner controls, not one long block
 *  - narration and visuals that build together rather than competing
 *  - faded worked examples: full, then partly blank, then unaided
 *  - distractors built from real misconceptions, so a wrong answer teaches
 *  - technical terms kept in English even in home-language lessons, because the
 *    final exam is written in English or Afrikaans
 */
class LessonGenerator
{
    public function __construct(private readonly AssistantProvider $provider)
    {
    }

    public function generate(Topic $topic, Language $language): TopicVersion
    {
        $version = TopicVersion::updateOrCreate(
            ['topic_id' => $topic->id, 'language_id' => $language->id],
            ['status' => TopicVersion::STATUS_GENERATING],
        );

        $sources = $topic->sources()
            ->whereNotNull('extracted_text')
            ->pluck('extracted_text')
            ->implode("\n\n---\n\n");

        $result = $this->provider->ask(
            $this->systemPrompt($topic, $language),
            $this->userPrompt($topic, $sources),
        );

        $payload = $this->parse($result['text']);

        DB::transaction(function () use ($version, $payload, $result) {
            $version->update([
                'lesson' => ['segments' => $payload['segments'] ?? []],
                'notes' => $payload['notes'] ?? null,
                'flashcards' => $payload['flashcards'] ?? [],
                'provider' => $this->provider->name(),
                'model' => $this->provider->model(),
                'cost_usd' => $result['cost_usd'],
                'generated_at' => now(),
                // Never straight to students: a human signs it off first.
                'status' => TopicVersion::STATUS_REVIEW,
            ]);

            $version->questions()->delete();

            foreach (($payload['questions'] ?? []) as $position => $question) {
                if (! isset($question['level'], $question['question'], $question['options'])) {
                    continue;
                }

                TopicQuestion::create([
                    'topic_version_id' => $version->id,
                    'level' => $question['level'],
                    'question' => $question['question'],
                    'options' => $question['options'],
                    'correct_index' => (int) ($question['correct_index'] ?? 0),
                    'explanation' => $question['explanation'] ?? '',
                    'position' => $position,
                ]);
            }
        });

        audit('topic.generated', $version, [
            'topic_id' => $topic->id,
            'language' => $language->code,
            'cost_usd' => $result['cost_usd'],
        ]);

        return $version->fresh();
    }

    private function systemPrompt(Topic $topic, Language $language): string
    {
        $subject = $topic->subject?->name ?? 'this subject';
        $level = collect($topic->subject?->ancestors() ?? [])->pluck('name')->implode(' · ');

        $languageRule = $language->isEnglish()
            ? 'Write everything in clear, simple English.'
            : <<<RULE
            Write the narration and explanations in {$language->name} ({$language->native_name}).
            Keep technical terms, formulas, units and diagram labels in English, and give the
            {$language->name} word in brackets the first time each term appears. South African
            final examinations are written in English, so the learner must recognise the English
            term while understanding the concept in their own language.
            RULE;

        return <<<PROMPT
        You write curriculum lessons for DX Student Help, a South African learning platform.
        Subject: {$subject}. Level: {$level}. Topic: {$topic->title}.

        {$languageRule}

        Teach like this:
        - Break the lesson into 6 to 10 segments, each 2 to 4 minutes when read aloud.
        - Every segment has narration text and a visual description that builds alongside it,
          step by step. The visual must add something the words do not.
        - Put one "pause and think" question inside each segment, before revealing the answer.
        - Include worked examples that fade: the first fully worked, the next with the final
          step blank, the next with the last two blank, until the learner works unaided.
        - Use South African contexts, rand, metric units and local examples.
        - Assume a phone screen and a learner who may be revising alone at night.

        Write assessment questions at five levels: basic, easy, intermediate, difficult, extreme.
        At least four per level. Every wrong option must be a real mistake a learner makes, and
        must carry a short note naming that misconception, so feedback can address it.

        Return strict JSON only, no markdown, with this shape:
        {
          "segments": [{"title": "", "narration": "", "visual": "", "check": {"question": "", "answer": ""}}],
          "notes": "one page of revision notes in markdown",
          "flashcards": [{"front": "", "back": ""}],
          "questions": [{"level": "basic", "question": "", "options": [{"text": "", "misconception": ""}],
                         "correct_index": 0, "explanation": ""}]
        }
        PROMPT;
    }

    private function userPrompt(Topic $topic, string $sources): string
    {
        $objectives = collect($topic->objectives ?? [])->implode('; ');

        return trim(<<<PROMPT
        Topic: {$topic->title}
        Summary: {$topic->summary}
        Learning objectives: {$objectives}

        Source material supplied by the school:
        {$sources}
        PROMPT);
    }

    /** @return array<string, mixed> */
    private function parse(string $text): array
    {
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text);

        return json_decode($clean, true) ?? [];
    }
}

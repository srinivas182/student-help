<?php

namespace App\Domains\Tutor\Services;

use App\Domains\Tutor\Models\TopicSource;
use Illuminate\Support\Facades\Storage;

/**
 * Turns whatever the school uploaded into plain text the generator can read.
 * Voice sources reuse the transcription pipeline built for voice notes.
 */
class SourceExtractor
{
    public function extract(TopicSource $source): void
    {
        $text = match ($source->kind) {
            TopicSource::KIND_TEXT => $source->extracted_text,
            TopicSource::KIND_PDF, TopicSource::KIND_DOCUMENT => $this->fromFile($source),
            default => null,
        };

        $source->update([
            'extracted_text' => $text,
            'extraction_status' => $text ? 'done' : 'failed',
        ]);
    }

    private function fromFile(TopicSource $source): ?string
    {
        if (! $source->path || ! Storage::disk('local')->exists($source->path)) {
            return null;
        }

        $absolute = Storage::disk('local')->path($source->path);

        // pdftotext ships with the deployment image; fall back to raw read.
        if ($source->kind === TopicSource::KIND_PDF && $this->hasPdfToText()) {
            $output = [];
            exec('pdftotext -layout '.escapeshellarg($absolute).' - 2>/dev/null', $output);

            $text = trim(implode("\n", $output));

            return $text !== '' ? $text : null;
        }

        $contents = @file_get_contents($absolute);

        return $contents ? mb_convert_encoding(strip_tags($contents), 'UTF-8', 'UTF-8') : null;
    }

    private function hasPdfToText(): bool
    {
        exec('which pdftotext', $output, $code);

        return $code === 0;
    }
}

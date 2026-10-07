<?php

use App\Domains\Content\Models\Resource;
use App\Domains\Tutoring\Models\TutorProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo files used to be plain text labelled application/pdf. The browser's
 * viewer refused them and the preview read as a broken platform rather than
 * as placeholder data.
 */
beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('seeds study material as files that actually open', function () {
    $resources = Resource::whereNotNull('path')->get();

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resource) {
        $contents = Storage::disk('local')->get($resource->path);

        if ($resource->mime_type === 'application/pdf') {
            expect($contents)->toStartWith('%PDF-', "{$resource->title} is not a real PDF")
                ->and(trim($contents))->toEndWith('%%EOF');
        }

        // The stored size must match the file, or downloads report the wrong length
        expect($resource->size)->toBe(strlen($contents));
    }
});

it('seeds tutor verification documents as real PDFs', function () {
    $documents = TutorProfile::with('documents')->get()->flatMap->documents;

    expect($documents)->not->toBeEmpty();

    foreach ($documents as $document) {
        expect(Storage::disk('local')->get($document->path))->toStartWith('%PDF-');
    }
});

it('gives every stored file an extension matching its type', function () {
    foreach (Resource::whereNotNull('path')->get() as $resource) {
        $extension = pathinfo($resource->path, PATHINFO_EXTENSION);

        expect($extension)->toBe(
            $resource->mime_type === 'audio/mpeg' ? 'mp3' : 'pdf',
            "{$resource->title} has extension .{$extension} for {$resource->mime_type}",
        );
    }
});

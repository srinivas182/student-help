<?php

use Illuminate\Support\Facades\File;

/**
 * Seeders run on production with --no-dev, where Faker is not installed.
 * A factory call in a seeder only fails on the server, which is the worst
 * place to find out.
 */
it('has no factory calls in seeders that run on production', function () {
    $offenders = [];

    foreach (File::files(database_path('seeders')) as $file) {
        // Strip comments first, so a line warning against factories is not
        // itself reported as a factory call.
        $code = preg_replace('~//[^\n]*~', '', File::get($file->getPathname()));
        $code = preg_replace('~/\*.*?\*/~s', '', (string) $code);

        if (preg_match('~::factory\s*\(~', (string) $code)
            || preg_match('~(?<![A-Za-z0-9_\\\\])fake\s*\(~', (string) $code)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBeEmpty(
        'These seeders use factories or fake(), which are unavailable on a --no-dev install: '
        .implode(', ', $offenders),
    );
});

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite silently accepts a query naming a column that does not exist; MySQL
 * rejects it. The suite runs on SQLite and production runs on MySQL, so a typo
 * in a column name passed every test and then broke the live dashboard.
 *
 * This walks the domain code for column references and checks them against the
 * real schema, which catches the same class of mistake without needing MySQL.
 */
it('references only columns that exist', function () {
    $tables = collect(Schema::getTableListing())
        ->mapWithKeys(fn (string $table) => [
            // Some drivers prefix the database name
            str_contains($table, '.') ? str($table)->afterLast('.')->toString() : $table => true,
        ]);

    $knownColumns = $tables->keys()
        ->flatMap(fn (string $table) => Schema::getColumnListing($table))
        ->unique()
        ->flip();

    $offenders = [];

    // Controllers query too — scanning only app/Domains missed them entirely
    $files = collect(File::allFiles(app_path('Domains')))
        ->merge(File::allFiles(app_path('Http')))
        ->merge(File::allFiles(app_path('Console')))
        ->filter(fn ($file) => $file->getExtension() === 'php');

    foreach ($files as $file) {
        $code = File::get($file->getPathname());

        // Only methods that are query-builder specific. where() and pluck()
        // are left out: collections have them too, and a collection key is not
        // a column, which produced false positives on perfectly good code.
        preg_match_all(
            "/->(?:whereNotNull|whereNull|orderBy|orderByDesc|groupBy|whereDate|whereIn)\(\s*'([a-z_]{3,40})'/",
            $code,
            $matches,
        );

        // Aliases created with "as <name>" in the same file are legitimate
        // targets for orderBy and are not table columns
        preg_match_all('/\bas\s+([a-z_]{3,40})\b/i', $code, $aliasMatches);
        $aliases = collect($aliasMatches[1])->map(fn ($a) => strtolower($a))->flip();

        foreach ($matches[1] as $column) {
            if (str_contains($column, '.') || $knownColumns->has($column) || $aliases->has($column)) {
                continue;
            }

            $offenders[] = $file->getFilename().': '.$column;
        }
    }

    expect(array_unique($offenders))->toBeEmpty(
        'These column names do not exist in the schema. SQLite tolerates them; MySQL will not: '
        .implode(', ', array_unique($offenders)),
    );
});

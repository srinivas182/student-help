<?php

namespace Database\Seeders;

use App\Domains\Tutor\Models\Language;
use Illuminate\Database\Seeder;

/**
 * South Africa's official languages. Which are active, and which have
 * text-to-speech, is managed by DX in admin — speech support varies sharply
 * between them, and a robotic voice nobody can follow is worse than clean text.
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['en', 'English', 'English', true, true],
            ['af', 'Afrikaans', 'Afrikaans', true, true],
            ['zu', 'isiZulu', 'isiZulu', true, true],
            ['xh', 'isiXhosa', 'isiXhosa', true, false],
            ['st', 'Sesotho', 'Sesotho', true, false],
            ['nso', 'Sepedi', 'Sepedi', true, false],
            ['tn', 'Setswana', 'Setswana', true, false],
            ['ts', 'Xitsonga', 'Xitsonga', false, false],
            ['ss', 'siSwati', 'siSwati', false, false],
            ['ve', 'Tshivenda', 'Tshivenḓa', false, false],
            ['nr', 'isiNdebele', 'isiNdebele', false, false],
        ];

        foreach ($languages as $position => [$code, $name, $native, $active, $tts]) {
            Language::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'native_name' => $native,
                    'is_active' => $active,
                    'tts_supported' => $tts,
                    'position' => $position,
                ],
            );
        }
    }
}

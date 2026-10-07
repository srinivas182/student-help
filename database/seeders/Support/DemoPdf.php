<?php

namespace Database\Seeders\Support;

/**
 * A real, valid single-page PDF, written by hand.
 *
 * The seeder used to write plain text into a .pdf and label it
 * application/pdf, so the browser's viewer refused it and the preview showed
 * "failed to load PDF" — which looks like a broken platform rather than
 * placeholder data. No library needed: the format is simple enough to emit
 * directly, and adding a PDF dependency for demo content is not worth it.
 */
class DemoPdf
{
    public static function make(string $title, array $lines = []): string
    {
        $body = array_merge([$title, ''], $lines, ['', 'Demo material for DX Student Help.']);

        $text = "BT\n/F1 16 Tf\n72 760 Td\n";
        $first = true;

        foreach ($body as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);

            if ($first) {
                $text .= "({$escaped}) Tj\n/F1 11 Tf\n";
                $first = false;

                continue;
            }

            $text .= "0 -20 Td ({$escaped}) Tj\n";
        }

        $text .= "ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                ."/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($text)." >>\nstream\n{$text}\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n"
            ."startxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }
}

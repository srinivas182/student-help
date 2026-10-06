<?php

namespace App\Domains\Tutoring\Services;

/**
 * Keeps conversations on the platform (SRS: MSG-03, MSG-07).
 *
 * Phone numbers, email addresses, social handles and external links are masked
 * before a message is stored or shown. Moderators still see the original text,
 * and every such view is audit-logged.
 *
 * This matters more here than on a general platform: adult tutors talk to
 * learners as young as 13, and moving a conversation to WhatsApp puts it
 * outside DX's safeguarding reach entirely.
 */
class ContentFilter
{
    private const MASK = '[contact details removed]';

    private const LINK_MASK = '[link removed]';

    /** South African and international formats, including spaced and dotted digits. */
    private const PATTERNS = [
        // +27 82 123 4567 / 0821234567 / 082-123-4567 / 082 123 4567
        '/(?:\+?\d{1,3}[\s.\-]?)?(?:\(?\d{2,4}\)?[\s.\-]?)?\d{3}[\s.\-]?\d{4}\b/',
        // Email addresses, including obfuscated "name at domain dot com"
        '/[\w.+\-]+@[\w\-]+\.[\w.\-]+/i',
        '/\b[\w.+\-]+\s+(?:at|\(at\))\s+[\w\-]+\s+(?:dot|\(dot\))\s+\w{2,}\b/i',
        // Social handles
        '/(?<![\w\/])@[A-Za-z0-9._]{3,30}\b/',
        // "whatsapp me on", "call me", followed by digits
        '/\b(?:whats\s?app|wa|call|text|sms|dm)\s*(?:me)?\s*(?:on|at)?\s*[:\-]?\s*\+?[\d\s.\-]{7,}/i',
    ];

    private const URL_PATTERN = '/\b(?:https?:\/\/|www\.)[^\s]+|\b[\w\-]+\.(?:com|co\.za|net|org|io|me)\b[^\s]*/i';

    /** @return array{body: string, masked: bool} */
    public function mask(string $text): array
    {
        $result = $text;

        foreach (self::PATTERNS as $pattern) {
            $result = preg_replace($pattern, self::MASK, $result) ?? $result;
        }

        $result = preg_replace(self::URL_PATTERN, self::LINK_MASK, $result) ?? $result;

        return ['body' => $result, 'masked' => $result !== $text];
    }

    /** MSG-07: prohibited words put a message in front of a moderator. */
    public function shouldFlag(string $text): bool
    {
        $words = (array) setting('prohibited_words', []);

        foreach ($words as $word) {
            if ($word !== '' && str_contains(mb_strtolower($text), mb_strtolower((string) $word))) {
                return true;
            }
        }

        return false;
    }
}

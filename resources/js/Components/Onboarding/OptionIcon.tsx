/**
 * An icon for each onboarding choice.
 *
 * Matched on the option's own name rather than a stored icon field, so the
 * curriculum can grow without anyone remembering to set an icon — a new
 * subject falls back to a sensible default rather than to a blank square.
 */

type IconName =
    | 'school'
    | 'college'
    | 'university'
    | 'public'
    | 'private'
    | 'certificate'
    | 'diploma'
    | 'grade'
    | 'year'
    | 'maths'
    | 'science'
    | 'language'
    | 'business'
    | 'computing'
    | 'engineering'
    | 'health'
    | 'arts'
    | 'humanities'
    | 'hospitality'
    | 'default';

/** Longest match wins, so "Public College" beats both "public" and "college". */
const RULES: [RegExp, IconName][] = [
    [/public\s+(school|college)/i, 'public'],
    [/private\s+(school|college)/i, 'private'],
    [/\buniversit/i, 'university'],
    [/\bcollege|tvet\b/i, 'college'],
    [/\bschool\b/i, 'school'],
    [/\bncv\b|vocational|certificate/i, 'certificate'],
    [/\bnated\b|diploma/i, 'diploma'],
    [/grade\s*\d+/i, 'grade'],
    [/\b(n[1-6]|level\s*\d+|\d+(st|nd|rd|th)\s+year)\b/i, 'year'],
    [/mathemat|accounting|statistic/i, 'maths'],
    [/physical science|life science|biolog|chemis|physics|agricultur/i, 'science'],
    [/language|english|isizulu|isixhosa|afrikaans|sepedi|setswana|sesotho|xitsonga|siswati|tshivenda|ndebele/i, 'language'],
    [/business|management|commerce|economic|finance|office/i, 'business'],
    [/comput|information tech|\bit\b|robotic|software/i, 'computing'],
    [/engineer|utility|technical|design/i, 'engineering'],
    [/health|nursing|medic/i, 'health'],
    [/art|media|creative|music|drama/i, 'arts'],
    // Before humanities, which also matches "tourism"
    [/hospitality|catering|food|tourism/i, 'hospitality'],
    [/history|geograph|social|legal|law|education/i, 'humanities'],
];

export function iconFor(name: string): IconName {
    for (const [pattern, icon] of RULES) {
        if (pattern.test(name)) {
            return icon;
        }
    }

    return 'default';
}

const PATHS: Record<IconName, string> = {
    school: 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-5h6v5M10 12h4',
    college: 'M3 21h18M4 21V10M20 21V10M8 21V10M16 21V10M12 21V10M2 10h20L12 4 2 10Z',
    university: 'M12 3 2 8l10 5 10-5-10-5ZM6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5M20 9v6',
    public: 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-5h6v5M19 4v3M19 4h3',
    private: 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6M12 9l2 1.5v2L12 14l-2-1.5v-2L12 9Z',
    certificate: 'M7 3h10a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2ZM9 7h6M9 11h4M12 16v5l2.5-1.5L17 21v-5',
    diploma: 'M12 3 2 8l10 5 10-5-10-5ZM6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5',
    grade: 'M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2V5ZM8 7h8M8 11h6',
    year: 'M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1ZM9 14h2M14 14h2',
    maths: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2ZM7 8h4M9 6v4M14 8h3M14 15h3M7 14h4M9 17l-2-2 2-2',
    science: 'M9 3v6L4.5 17A2 2 0 0 0 6.2 20h11.6a2 2 0 0 0 1.7-3L15 9V3M8 3h8M8.5 13h7',
    language: 'M4 5h16M12 3v2M9 5c0 5-2 8-5 10M11 5c0 5 2 8 5 10M13 21l4-9 4 9M14.5 18h5',
    business: 'M3 8h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8ZM9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18',
    computing: 'M3 5h18v11H3zM8 20h8M12 16v4M7 9h4M7 12h7',
    engineering: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1',
    health: 'M12 20s-7-4.5-7-9.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 7 3.5c0 5-7 9.5-7 9.5ZM9 12h2l1-2 1.5 4 1-2h1.5',
    arts: 'M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.2 0-1 .9-1.8 1.9-1.8H17a4.5 4.5 0 0 0 4.5-4.5C21.5 6.2 17.3 3 12 3ZM7 12h.01M9.5 8.5h.01M14.5 8h.01',
    humanities: 'M3 5h7a3 3 0 0 1 3 3v12a2.5 2.5 0 0 0-2.5-2.5H3V5ZM21 5h-7a3 3 0 0 0-3 3v12a2.5 2.5 0 0 1 2.5-2.5H21V5Z',
    hospitality: 'M4 15h16a8 8 0 0 0-16 0ZM2 18h20M12 7V5M10 5h4',
    default: 'M12 3 2 8l10 5 10-5-10-5ZM6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5',
};

export default function OptionIcon({
    name,
    className = 'h-7 w-7',
}: {
    name: string;
    className?: string;
}) {
    return (
        <svg
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.6}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={PATHS[iconFor(name)]} />
        </svg>
    );
}

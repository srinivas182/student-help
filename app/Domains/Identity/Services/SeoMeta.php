<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Portal;
use Illuminate\Http\Request;

/**
 * Page metadata for search engines and answer engines.
 *
 * Two jobs that pull in different directions: the landing pages want to be
 * found and quoted, and everything behind a login wants to be left alone. So
 * the default is noindex, and only the public pages opt in.
 *
 * The structured data matters more than the meta tags now. An assistant asked
 * "where can a South African matric student get free help with maths" answers
 * from facts it can parse, not from a description tag.
 */
class SeoMeta
{
    /** Routes that should be found. Everything else is noindex. */
    private const PUBLIC_ROUTES = ['home', 'login', 'register', 'password.request'];

    public function __construct(private readonly Portal $portal)
    {
    }

    public function for(Request $request): array
    {
        $portal = $this->portal->current($request);
        $routeName = $request->route()?->getName();
        $isPublic = in_array($routeName, self::PUBLIC_ROUTES, true);

        $meta = $portal === Portal::TEACHER
            ? $this->teacher($routeName)
            : $this->student($routeName);

        return [
            ...$meta,
            'canonical' => $this->canonical($request, $portal),
            'robots' => $isPublic
                ? 'index, follow, max-snippet:-1, max-image-preview:large'
                : 'noindex, nofollow',
            'locale' => 'en_ZA',
            'siteName' => (string) config("portals.{$portal}.name"),
            'structuredData' => $isPublic ? $this->structuredData($request, $portal) : null,
        ];
    }

    private function student(?string $route): array
    {
        return match ($route) {
            'register' => [
                'title' => 'Create a free account — DX Student Help',
                'description' => 'Join free in two minutes and ask a verified South African tutor '
                    .'for help with your schoolwork tonight. Grade 8 to university.',
            ],
            'login' => [
                'title' => 'Sign in — DX Student Help',
                'description' => 'Sign in to ask a tutor, carry on a lesson, or revise with past papers.',
            ],
            default => [
                'title' => 'Free homework help from verified tutors — DX Student Help',
                'description' => 'Stuck on homework? Ask a verified South African tutor for free. '
                    .'CAPS lessons in 11 languages, past papers and memos, and practice questions '
                    .'for Grade 8 to university. Works on any phone, light on data.',
            ],
        };
    }

    private function teacher(?string $route): array
    {
        return match ($route) {
            'register' => [
                'title' => 'Apply to tutor — The X Teacher Help',
                'description' => 'Apply in ten minutes to help South African learners in the subjects '
                    .'you know. Answer questions when it suits you. No timetable, no minimum hours.',
            ],
            'login' => [
                'title' => 'Sign in — The X Teacher Help',
                'description' => 'Sign in to see questions waiting in your subjects and manage your classes.',
            ],
            default => [
                'title' => 'Volunteer as a verified tutor — The X Teacher Help',
                'description' => 'Teachers, graduates and university students: answer questions in '
                    .'your subjects when it suits you, share your notes once, and earn a contribution '
                    .'certificate. Free to join, verified in about 48 hours.',
            ],
        };
    }

    private function canonical(Request $request, string $portal): string
    {
        // Always the bare domain, so www and non-www do not compete
        $path = ltrim($request->path() === '/' ? '' : $request->path(), '/');

        return rtrim($this->portal->urlFor($portal, $path), '/').($path === '' ? '/' : '');
    }

    /**
     * Facts an answer engine can quote, rather than prose it has to interpret.
     */
    private function structuredData(Request $request, string $portal): array
    {
        $url = $this->portal->urlFor($portal);
        $isStudent = $portal === Portal::STUDENT;

        $organisation = [
            '@type' => 'EducationalOrganization',
            '@id' => $url.'#organisation',
            'name' => (string) config("portals.{$portal}.name"),
            'legalName' => 'The X Student Help (PTY) LTD',
            'url' => $url,
            'areaServed' => ['@type' => 'Country', 'name' => 'South Africa'],
            'description' => $isStudent
                ? 'Free academic help for South African learners from verified tutors, with '
                    .'curriculum lessons in 11 official languages.'
                : 'A platform for South African teachers, graduates and university students to '
                    .'volunteer academic help to learners.',
            'knowsLanguage' => [
                'English', 'isiZulu', 'isiXhosa', 'Afrikaans', 'Sepedi', 'Setswana',
                'Sesotho', 'Xitsonga', 'siSwati', 'Tshivenda', 'isiNdebele',
            ],
        ];

        $graph = [$organisation, [
            '@type' => 'WebSite',
            '@id' => $url.'#website',
            'url' => $url,
            'name' => (string) config("portals.{$portal}.name"),
            'publisher' => ['@id' => $url.'#organisation'],
            'inLanguage' => 'en-ZA',
        ]];

        if ($request->route()?->getName() === 'home') {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => collect($isStudent ? $this->studentFaq() : $this->teacherFaq())
                    ->map(fn (array $item) => [
                        '@type' => 'Question',
                        'name' => $item[0],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]],
                    ])->all(),
            ];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** Mirrors the questions on the landing page, so the two never disagree. */
    private function studentFaq(): array
    {
        return [
            ['Does DX Student Help cost anything?',
                'No. Asking a verified tutor for help is free. Some extras may be paid later, but '
                .'help from a tutor stays free.'],
            ['Who are the tutors?',
                'University students, teachers and graduates. Every tutor is checked by the DX team '
                .'— identity, qualifications and police clearance — before they can speak to a learner.'],
            ['Can I join if I am under 18?',
                'Yes, with a parent or guardian\'s approval. DX emails them to explain the platform '
                .'and ask permission, as South African law requires.'],
            ['Which grades and subjects are covered?',
                'CAPS Grades 8 to 12, NCV and NATED college programmes, and university '
                .'qualifications, across the full range of school subjects.'],
            ['What languages are lessons available in?',
                'Lessons are written in South Africa\'s 11 official languages. Technical terms and '
                .'formulas stay in English, because the NSC paper is written in English.'],
            ['How much data does it use?',
                'Very little. Lessons are text and audio rather than video, and can be downloaded on '
                .'wifi to use later.'],
        ];
    }

    private function teacherFaq(): array
    {
        return [
            ['Do tutors get paid?',
                'Not at launch. Tutoring on DX is voluntary. Tutors receive recognition, a public '
                .'rating and a printable contribution certificate.'],
            ['How much time does tutoring take?',
                'Entirely up to the tutor. Some answer one question a week, others several a day. '
                .'You accept only the questions you choose.'],
            ['What is needed to apply?',
                'A South African ID, your highest qualification certificate, and a SAPS police '
                .'clearance certificate, because tutors work with learners under 18.'],
            ['Who can become a tutor?',
                'University students, teachers, graduates and working professionals who know a '
                .'subject and can explain it patiently.'],
            ['Can I tutor in isiZulu or Afrikaans?',
                'Yes. Many learners understand better in their home language, and DX needs tutors '
                .'who can teach in it.'],
        ];
    }
}

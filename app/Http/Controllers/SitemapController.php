<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Portal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A sitemap of the pages that should be found.
 *
 * Deliberately short: only the public pages belong here. Lesson and community
 * pages sit behind a login and carry learners' own questions.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly Portal $portal)
    {
    }

    /**
     * Served per domain rather than as a static file: both portals share one
     * public directory, so a static robots.txt would point the teacher site at
     * the student sitemap.
     */
    public function robots(Request $request): Response
    {
        $portal = $this->portal->current($request);

        $lines = [
            "# The landing pages should be found. Everything behind a login should",
            "# not: those pages carry learners' questions and conversations.",
            '',
            'User-agent: *',
            'Allow: /$',
            'Allow: /login',
            'Allow: /register',
        ];

        foreach ([
            'dashboard', 'requests', 'conversations', 'classes', 'community',
            'study-groups', 'learn', 'assessment', 'resources', 'progress',
            'billing', 'moderation', 'admin', 'tutor', 'review', 'assistant',
            'notifications', 'onboarding', 'profile', 'search', 'consent',
            'invitations', 'two-factor', 'horizon',
        ] as $path) {
            $lines[] = 'Disallow: /'.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$this->portal->urlFor($portal, 'sitemap.xml');

        $body = implode("\n", $lines);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Request $request): Response
    {
        $portal = $this->portal->current($request);

        $pages = [
            ['', '1.0', 'weekly'],
            ['login', '0.5', 'monthly'],
            ['register', '0.8', 'monthly'],
        ];

        $urls = collect($pages)->map(function (array $page) use ($portal) {
            [$path, $priority, $frequency] = $page;

            return sprintf(
                "    <url>\n        <loc>%s</loc>\n        <changefreq>%s</changefreq>\n"
                ."        <priority>%s</priority>\n    </url>",
                e($this->portal->urlFor($portal, $path)),
                $frequency,
                $priority,
            );
        })->implode("\n");

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

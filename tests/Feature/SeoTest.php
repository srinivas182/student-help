<?php

use Database\Seeders\DatabaseSeeder;

/**
 * Two jobs pulling in different directions: the landing pages should be found
 * and quoted, and everything behind a login should be left alone.
 */
beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('gives the student landing page a title and description worth indexing', function () {
    $html = $this->get('https://thexstudenthelp.co.za/')->getContent();

    expect($html)->toContain('<title inertia>Free homework help from verified tutors')
        ->and($html)->toContain('name="description"')
        ->and($html)->toContain('verified South African tutor')
        ->and($html)->toContain('content="index, follow');
});

it('gives the teacher landing page its own title, not the student one', function () {
    $html = $this->get('https://thexteacherhelp.co.za/')->getContent();

    expect($html)->toContain('Volunteer as a verified tutor')
        ->and($html)->not->toContain('Stuck on homework');
});

it('keeps pages behind a login out of search results', function () {
    $student = App\Models\User::where('email', 'student@dxstudenthelp.co.za')->firstOrFail();

    $html = $this->actingAs($student)->get('https://thexstudenthelp.co.za/dashboard')->getContent();

    expect($html)->toContain('content="noindex, nofollow"');
});

it('publishes structured data an answer engine can quote', function () {
    $html = $this->get('https://thexstudenthelp.co.za/')->getContent();

    expect($html)->toContain('application/ld+json')
        ->and($html)->toContain('EducationalOrganization')
        ->and($html)->toContain('FAQPage')
        // The facts that make the platform findable for the right questions
        ->and($html)->toContain('South Africa')
        ->and($html)->toContain('isiZulu');
});

it('does not leak structured data onto pages behind a login', function () {
    $student = App\Models\User::where('email', 'student@dxstudenthelp.co.za')->firstOrFail();

    expect($this->actingAs($student)->get('https://thexstudenthelp.co.za/dashboard')->getContent())
        ->not->toContain('application/ld+json');
});

it('points the canonical link at the bare domain, never www', function () {
    $html = $this->get('https://www.thexstudenthelp.co.za/')->getContent();

    expect($html)->toContain('rel="canonical" href="https://thexstudenthelp.co.za/"')
        ->and($html)->not->toContain('canonical" href="https://www.');
});

it('serves a robots file per domain, each naming its own sitemap', function () {
    $student = $this->get('https://thexstudenthelp.co.za/robots.txt');
    $teacher = $this->get('https://thexteacherhelp.co.za/robots.txt');

    $student->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($student->getContent())->toContain('Sitemap: https://thexstudenthelp.co.za/sitemap.xml')
        ->and($student->getContent())->toContain('Disallow: /conversations')
        // A static file would have pointed both domains at the same sitemap
        ->and($teacher->getContent())->toContain('Sitemap: https://thexteacherhelp.co.za/sitemap.xml');
});

it('serves a sitemap listing only public pages', function () {
    $response = $this->get('https://thexstudenthelp.co.za/sitemap.xml');

    $response->assertOk();

    expect($response->getContent())->toContain('<urlset')
        ->and($response->getContent())->toContain('https://thexstudenthelp.co.za/')
        ->and($response->getContent())->toContain('register')
        // Nothing behind a login belongs in a sitemap
        ->and($response->getContent())->not->toContain('dashboard')
        ->and($response->getContent())->not->toContain('conversations');
});

it('describes the link for sharing, since this audience shares on WhatsApp', function () {
    $html = $this->get('https://thexstudenthelp.co.za/')->getContent();

    expect($html)->toContain('property="og:title"')
        ->and($html)->toContain('property="og:description"')
        ->and($html)->toContain('property="og:url"')
        ->and($html)->toContain('og:locale" content="en_ZA');
});

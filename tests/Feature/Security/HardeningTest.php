<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use App\Rules\SafeUrl;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(18),
        'onboarding_completed_at' => now(),
    ]);
});

it('sends the security headers on every page', function () {
    $response = $this->actingAs($this->student)->get(route('dashboard'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("object-src 'none'");
});

it('allows PayFast as a form target but nothing else', function () {
    $csp = $this->actingAs($this->student)
        ->get(route('dashboard'))
        ->headers->get('Content-Security-Policy');

    expect($csp)->toContain('payfast.co.za')
        ->and($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("frame-ancestors 'self'");
});

it('does not send HSTS over plain http', function () {
    expect($this->actingAs($this->student)->get(route('dashboard'))->headers->has('Strict-Transport-Security'))
        ->toBeFalse();
});

it('rejects a javascript: link where a web address is expected', function () {
    foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'file:///etc/passwd'] as $url) {
        $validator = Validator::make(['link' => $url], ['link' => [new SafeUrl]]);

        expect($validator->fails())->toBeTrue("Expected {$url} to be rejected");
    }
});

it('rejects links pointing at the server\'s own network', function () {
    foreach (['http://localhost/admin', 'http://127.0.0.1:6379', 'http://169.254.169.254/latest/meta-data'] as $url) {
        $validator = Validator::make(['link' => $url], ['link' => [new SafeUrl]]);

        expect($validator->fails())->toBeTrue("Expected {$url} to be rejected");
    }
});

it('accepts an ordinary https link', function () {
    $validator = Validator::make(
        ['link' => 'https://www.education.gov.za/past-papers'],
        ['link' => [new SafeUrl]],
    );

    expect($validator->fails())->toBeFalse();
});

it('throttles repeated searching rather than letting it run unbounded', function () {
    $this->actingAs($this->student);

    $lastStatus = 200;

    foreach (range(1, 70) as $attempt) {
        $lastStatus = $this->getJson(route('search.quick', ['q' => 'maths']))->status();

        if ($lastStatus === 429) {
            break;
        }
    }

    expect($lastStatus)->toBe(429);
});

it('limits how fast a student can open new help requests', function () {
    $subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->firstOrFail();
    $this->student->subjects()->syncWithoutDetaching([$subject->id => ['role' => 'subject']]);

    $this->actingAs($this->student);

    $statuses = collect(range(1, 8))->map(fn ($n) => $this->post(route('requests.store'), [
        'subject_id' => $subject->id,
        'topic' => "Question {$n}",
        'description' => 'Please help me understand this properly.',
    ])->status());

    expect($statuses)->toContain(429);
});

it('keeps the PayFast webhook reachable but rate limited', function () {
    $routes = collect(app('router')->getRoutes())->first(
        fn ($route) => $route->getName() === 'billing.notify',
    );

    expect($routes->gatherMiddleware())->toContain('throttle:webhooks');
});

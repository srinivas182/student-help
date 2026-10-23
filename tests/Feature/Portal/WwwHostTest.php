<?php

use App\Domains\Identity\Portal;
use Illuminate\Http\Request;

/**
 * www.example.com and example.com are the same site.
 *
 * The portal was matched by exact hostname, so the www address never matched
 * and fell through to the default — a teacher arriving at www.theirdomain saw
 * the student portal, with nothing on the page to say why.
 */
beforeEach(function () {
    config([
        'portals.student.host' => 'thexstudenthelp.co.za',
        'portals.teacher.host' => 'thexteacherhelp.co.za',
        'portals.allow_shared_host' => false,
    ]);

    $this->portal = app(Portal::class);
});

function requestFor(string $host): Request
{
    return Request::create("https://{$host}/", 'GET');
}

it('resolves the teacher portal with and without www', function () {
    expect($this->portal->current(requestFor('thexteacherhelp.co.za')))->toBe(Portal::TEACHER)
        ->and($this->portal->current(requestFor('www.thexteacherhelp.co.za')))->toBe(Portal::TEACHER);
});

it('resolves the student portal with and without www', function () {
    expect($this->portal->current(requestFor('thexstudenthelp.co.za')))->toBe(Portal::STUDENT)
        ->and($this->portal->current(requestFor('www.thexstudenthelp.co.za')))->toBe(Portal::STUDENT);
});

it('ignores the case of the hostname', function () {
    expect($this->portal->current(requestFor('WWW.ThexTeacherHelp.CO.ZA')))->toBe(Portal::TEACHER);
});

it('does not treat a www address as an unknown host', function () {
    config(['portals.allow_shared_host' => true]);

    // Shared-host mode is for development and unknown hostnames. A www address
    // is neither, or the wrong-door login message would never fire on it.
    expect($this->portal->isSharedHost(requestFor('www.thexteacherhelp.co.za')))->toBeFalse()
        ->and($this->portal->isSharedHost(requestFor('localhost')))->toBeTrue();
});

it('only strips a leading www label, not any host starting with those letters', function () {
    // wwwtest.example.com is a different host entirely, not a www alias
    expect($this->portal->current(requestFor('wwwtest.thexteacherhelp.co.za')))
        ->toBe(Portal::STUDENT);
});

it('sends people to the bare domain, never the www one', function () {
    expect($this->portal->urlFor(Portal::TEACHER))->toContain('thexteacherhelp.co.za')
        ->and($this->portal->urlFor(Portal::TEACHER))->not->toContain('www.');
});

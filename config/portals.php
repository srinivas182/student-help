<?php

/**
 * Two front doors, one platform.
 *
 * Students use the student domain, teachers the teacher domain. Both are served
 * by the same application, database and deployment; the host decides which
 * portal a visitor sees. Anyone arriving at the wrong door is redirected to
 * their own, with a link so nobody gets stuck.
 */
return [

    'student' => [
        'name' => env('STUDENT_PORTAL_NAME', 'DX Student Help'),
        'host' => env('STUDENT_PORTAL_HOST', 'student-help.rightally.io'),
        'roles' => ['student'],
        'home' => 'dashboard',
        'tagline' => 'Get help from verified tutors, in your subjects.',
    ],

    'teacher' => [
        'name' => env('TEACHER_PORTAL_NAME', 'The X Teacher Help'),
        'host' => env('TEACHER_PORTAL_HOST', 'teacher-help.rightally.io'),
        'roles' => ['tutor', 'moderator', 'admin', 'super_admin'],
        'home' => 'tutor.queue',
        'tagline' => 'Support your students, share material, host classes.',
    ],

    /**
     * When the application is reached on any other host (local development,
     * an IP address, a staging URL), both portals are served from it so that
     * nothing is blocked. Set to false in production once DNS is in place.
     */
    'allow_shared_host' => env('PORTAL_ALLOW_SHARED_HOST', true),
];

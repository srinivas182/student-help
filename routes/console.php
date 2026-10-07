<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Escalate unaccepted requests and auto-close resolved ones
Schedule::command('requests:maintain')->everyFifteenMinutes()->withoutOverlapping();

// Housekeeping: expired codes, devices, old delivery logs, stale bypasses
Schedule::command('platform:maintain')->dailyAt('03:15')->withoutOverlapping();

// Spaced review nudges, once a day at a time a learner might actually act on
Schedule::command('reviews:remind')->dailyAt('16:00')->withoutOverlapping();

// Keep Horizon's metrics useful
Schedule::command('horizon:snapshot')->everyFiveMinutes();

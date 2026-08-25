<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nothing runs a scheduler in this project yet, and weekly peladas don't
// need one — the organizer opening their series tops the calendar up. This
// only makes the same work happen unattended once `schedule:work` exists.
Schedule::command('series:generate')->dailyAt('04:00');

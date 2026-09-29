<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Periodic tasks: create the occurrences that are due (templates with an RRULE)
Artisan::command('tasks:generate-periodic', function () {
    $created = \App\Services\Tasks\TaskGenerator::runPeriodic();
    $this->info("Created {$created} periodic task(s).");
})->purpose('Create the periodic tasks that are due');

\Illuminate\Support\Facades\Schedule::command('tasks:generate-periodic')->everyFifteenMinutes()->withoutOverlapping();

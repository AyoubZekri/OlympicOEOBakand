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

// Members added before accounts existed: give each one its user account (generated email when none, random password)
Artisan::command('members:create-accounts', function () {
    $count = 0;
    \App\Models\Individual::whereNull('user_id')->orderBy('id')->each(function ($member) use (&$count) {
        \App\Services\MemberAccount::sync($member);
        $count++;
    });
    $this->info("Created {$count} member account(s).");
})->purpose('Create the user accounts of the members that have none');

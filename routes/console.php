<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Members added before accounts existed: give each one its user account (generated email when none, random password)
Artisan::command('members:create-accounts', function () {
    $count = 0;
    \App\Models\Individual::whereNull('user_id')->orderBy('id')->each(function ($member) use (&$count) {
        \App\Services\MemberAccount::sync($member);
        $count++;
    });
    $this->info("Created {$count} member account(s).");
})->purpose('Create the user accounts of the members that have none');

// The Android app's notifications: a test one to a user (by email or id), with Firebase's answer
Artisan::command('push:test {user}', function (string $user) {
    $found = \App\Models\User::where('email', $user)->orWhere('id', $user)->first();
    if (!$found) {
        return $this->error('لا يوجد مستخدم بهذا البريد أو الرقم');
    }
    $this->line(\App\Services\Push::test($found));
})->purpose('Send a test notification to a user\'s phone');

// Periodic tasks: create the ones that are due, and tell the appointments' members whose alert time came (the tasks'
// lists also do it when opened, so it works without cron too)
Artisan::command('tasks:generate', function () {
    \App\Services\TaskSchedule::generateDue(true);
    $this->info('Periodic tasks checked.');
})->purpose('Create the periodic tasks that are due');
\Illuminate\Support\Facades\Schedule::command('tasks:generate')->everyFiveMinutes();

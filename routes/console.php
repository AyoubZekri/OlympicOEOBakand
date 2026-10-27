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

// The Android app's notifications: a test one to a user (by email or id), with Firebase's answer; with no user, the
// accounts signed in on a phone (that have a token)
Artisan::command('push:test {user?}', function (?string $user = null) {
    $found = $user ? \App\Models\User::where('email', $user)->orWhere('id', $user)->first() : null;
    if (!$found) {
        if ($user) {
            $this->error('لا يوجد مستخدم بهذا البريد أو الرقم');
        }
        $phones = \App\Models\User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->get(['id', 'name', 'email']);
        if ($phones->isEmpty()) {
            return $this->warn('لا يوجد حساب داخل من تطبيق الأندرويد (لا token)');
        }
        $this->info('الحسابات الداخلة من الهاتف — جرب: php artisan push:test <الرقم>');
        $this->table(['الرقم', 'الاسم', 'البريد'], $phones->map(fn ($u) => [$u->id, $u->name, $u->email])->all());

        return;
    }
    $this->line(\App\Services\Push::test($found));
})->purpose('Send a test notification to a user\'s phone');

// The app's alerts as Firebase notifications: each new one to the phones of who it concerns, once
Artisan::command('alerts:push', function () {
    $this->info('Sent: ' . \App\Services\Alerts\AlertPusher::run());
})->purpose('Send the new alerts to the phones (Firebase)');
\Illuminate\Support\Facades\Schedule::command('alerts:push')->everyFiveMinutes()->withoutOverlapping();

// Periodic tasks: create the ones that are due, and tell the appointments' members whose alert time came (the tasks'
// lists also do it when opened, so it works without cron too)
Artisan::command('tasks:generate', function () {
    \App\Services\TaskSchedule::generateDue(true);
    $this->info('Periodic tasks checked.');
})->purpose('Create the periodic tasks that are due');
\Illuminate\Support\Facades\Schedule::command('tasks:generate')->everyFiveMinutes();

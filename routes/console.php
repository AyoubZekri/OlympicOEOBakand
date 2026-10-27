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

// What the server sees for a user (by email or id): their phone, their rights, their alerts now and whether each was sent
Artisan::command('alerts:check {user}', function (string $user) {
    $found = \App\Models\User::where('email', $user)->orWhere('id', $user)->first();
    if (!$found) {
        return $this->error('لا يوجد مستخدم بهذا البريد أو الرقم');
    }
    $this->line('الهاتف: ' . ($found->fcm_token ? 'مربوط (token موجود)' : 'غير مربوط — لم يدخل من تطبيق الأندرويد'));
    $this->line('الدور: ' . ($found->role?->name ?? 'بدون دور') . ' · الغيابات: ' . (\App\Services\RolePermissions::can($found, 'absences', 'view') ? 'نعم' : 'لا (لا تنبيهات غياب)'));
    $sent = \Illuminate\Support\Facades\DB::table('alert_pushes')->where('user_id', $found->id)->pluck('key')->flip();
    $alerts = \App\Services\Alerts\AlertPusher::alerts($found);
    if (!$alerts) {
        return $this->warn('لا توجد تنبيهات حالياً لهذا المستخدم');
    }
    $this->table(['التنبيه', 'العنوان', 'أُرسل؟'], array_map(fn ($a) => [$a['key'], $a['title'], isset($sent[$a['key']]) ? 'نعم' : 'لا'], $alerts));
})->purpose('Show what the server sees for a user\'s notifications');

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

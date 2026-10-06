<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tasks created automatically when a match, a training session or a meeting is created
        \App\Services\Tasks\TaskEventHooks::register();

        // The login tokens: checked from a file a minute long, not from the database on every request
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
        \App\Models\User::saved(fn ($user) => \App\Models\PersonalAccessToken::forgetUser($user->id));

        // Any record saved or deleted (also by the scheduled tasks) raises the alerts' version
        $bump = function (string $event, array $data) {
            if (!($data[0] ?? null) instanceof \Laravel\Sanctum\PersonalAccessToken) {
                \App\Services\AlertsVersion::bump();
            }
        };
        \Illuminate\Support\Facades\Event::listen('eloquent.saved: *', $bump);
        \Illuminate\Support\Facades\Event::listen('eloquent.deleted: *', $bump);
    }
}

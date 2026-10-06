<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * "Has anything changed?" for the alerts, without the database: one number kept in a file,
 * raised after every change (a saved request, a model saved or deleted). The pages ask for it every few seconds
 * and reload their alerts only when it moves.
 */
class AlertsVersion
{
    private const KEY = 'alerts.version';

    /** Raised once per request at most */
    private static bool $bumped = false;

    private static function store()
    {
        // Always a file: never a database connection for this
        return Cache::store('file');
    }

    public static function current(): string
    {
        $version = self::store()->get(self::KEY);
        if ($version === null) {
            $version = time();
            self::store()->forever(self::KEY, $version);
        }

        return (string) $version;
    }

    public static function bump(): void
    {
        if (self::$bumped) {
            return;
        }
        self::$bumped = true;
        try {
            self::store()->forever(self::KEY, ((int) self::store()->get(self::KEY, time())) + 1);
        } catch (\Throwable $e) {
            // Only the alerts refresh later (on their next full reload)
        }
    }

    /** Tests: a new request may raise it again */
    public static function reset(): void
    {
        self::$bumped = false;
    }
}

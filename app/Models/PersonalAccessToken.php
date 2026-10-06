<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * The login tokens. Every request checks its token (and loads its user): kept a minute in a file,
 * so the database is not asked again and again. A token deleted (logout) or its user changed is asked again at once.
 */
class PersonalAccessToken extends SanctumToken
{
    /** Seconds a checked token is kept */
    public const KEEP = 60;

    protected $table = 'personal_access_tokens';

    private static function store()
    {
        return Cache::store('file');
    }

    public static function cacheKey($id): string
    {
        return 'auth.token.' . $id;
    }

    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            return parent::findToken($token);
        }

        [$id, $plain] = explode('|', $token, 2);
        $key = self::cacheKey($id);

        try {
            $cached = self::store()->get($key);
        } catch (\Throwable $e) {
            $cached = null;
        }
        if ($cached instanceof static) {
            return hash_equals($cached->token, hash('sha256', $plain)) ? $cached : null;
        }

        $found = parent::findToken($token);
        if ($found) {
            $found->loadMissing('tokenable');
            try {
                self::store()->put($key, $found, self::KEEP);
            } catch (\Throwable $e) {
                // Only not kept
            }
        }

        return $found;
    }

    protected static function booted(): void
    {
        $forget = fn (self $t) => self::forget($t->id);
        static::deleted($forget);
        static::updated($forget);
    }

    public static function forget($id): void
    {
        try {
            self::store()->forget(self::cacheKey($id));
        } catch (\Throwable $e) {
        }
    }

    /** A user changed (role, name…): their tokens are checked again on the next request */
    public static function forgetUser($userId): void
    {
        static::where('tokenable_id', $userId)->pluck('id')->each(fn ($id) => self::forget($id));
    }
}

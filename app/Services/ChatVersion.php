<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * "Has this conversation changed?" without the database: one number per task kept in a file, raised when a message
 * is written there or the other side reads it. An open chat asks for it every few seconds (no login check: there is
 * nothing in it but the number) and fetches the new messages only when it moves.
 */
class ChatVersion
{
    private static function key(int $taskId): string
    {
        return "chat.version.{$taskId}";
    }

    public static function current(int $taskId): int
    {
        return (int) Cache::store('file')->get(self::key($taskId), 0);
    }

    /** Someone has this chat open now (said with every check): no notification for its messages while they look */
    public static function viewing(int $taskId, int $userId): void
    {
        Cache::store('file')->put("chat.viewing.{$taskId}.{$userId}", 1, 8);
    }

    public static function isViewing(int $taskId, int $userId): bool
    {
        return Cache::store('file')->has("chat.viewing.{$taskId}.{$userId}");
    }

    public static function bump(int $taskId): void
    {
        // Milliseconds: two changes in the same second still differ
        Cache::store('file')->put(self::key($taskId), (int) floor(microtime(true) * 1000), now()->addDays(30));
    }
}

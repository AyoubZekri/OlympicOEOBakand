<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notifications to the Android app's phones (Firebase Cloud Messaging, HTTP v1). Each user has one phone's token
 * (users.fcm_token, given by the app once signed in). The server signs in to Google with the Firebase project's
 * service account (its JSON key: config('services.fcm.credentials')); without it, nothing is sent.
 * Sent after the answer (defer), so a request never waits for Google, and never fails because of it.
 */
class Push
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * One notification to these users (ids; none: nobody), except who caused it; a tap opens $data['task_id'] in the app
     *
     * @param  int|iterable<int|null>|null  $users
     */
    public static function send(int|iterable|null $users, string $title, string $body, array $data = [], ?int $except = null): void
    {
        $ids = collect(is_iterable($users) ? $users : [$users])->filter()->map(fn ($id) => (int) $id)->unique()
            ->reject(fn ($id) => $except !== null && $id === $except)->values();
        if ($ids->isEmpty() || !self::credentials()) {
            return;
        }
        $tokens = User::whereIn('id', $ids)->whereNotNull('fcm_token')->where('fcm_token', '!=', '')->pluck('fcm_token', 'id');
        if ($tokens->isEmpty()) {
            return;
        }
        $body = mb_strlen($body) > 160 ? mb_substr($body, 0, 157) . '…' : $body;

        defer(function () use ($tokens, $title, $body, $data) {
            foreach ($tokens as $userId => $token) {
                self::deliver((int) $userId, $token, $title, $body, $data);
            }
        });
    }

    /**
     * A test notification to one user, sent now, with what happened in words (php artisan push:test): the key's
     * project, the phone's token, Google's sign-in and FCM's answer
     */
    public static function test(User $user): string
    {
        $key = self::credentials();
        if (!$key) {
            return 'لا يوجد مفتاح Firebase صالح في: ' . config('services.fcm.credentials');
        }
        $lines = ['مشروع المفتاح: ' . $key['project_id']];
        if (!$user->fcm_token) {
            return implode("\n", [...$lines, 'هذا المستخدم ليس له token (لم يدخل من تطبيق الأندرويد بعد)']);
        }
        Cache::forget('fcm.access_token');
        if (!self::accessToken($key)) {
            return implode("\n", [...$lines, 'رفض Google الدخول بهذا المفتاح (المفتاح خاطئ أو محذوف)']);
        }
        $response = self::deliver($user->id, $user->fcm_token, 'تجربة الإشعارات', 'إذا وصلك هذا، الإشعارات تعمل', []);

        return implode("\n", [...$lines, 'جواب FCM: ' . ($response ? $response->status() . ' ' . $response->body() : 'لا جواب')]);
    }

    private static function deliver(int $userId, string $token, string $title, string $body, array $data): ?\Illuminate\Http\Client\Response
    {
        try {
            $key = self::credentials();
            $access = self::accessToken($key);
            if (!$access) {
                Log::warning('Push not sent: Google refused the Firebase key');

                return null;
            }
            $response = Http::withToken($access)->timeout(8)->post(
                "https://fcm.googleapis.com/v1/projects/{$key['project_id']}/messages:send",
                ['message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => ['channel_id' => 'tasks', 'sound' => 'default', 'color' => '#F97316', 'icon' => 'ic_stat_notify'],
                    ],
                ]],
            );
            // The app was removed, or the phone signed in elsewhere: the token is no longer good
            if (in_array($response->status(), [400, 404], true) && str_contains($response->body(), 'UNREGISTERED')
                || $response->status() === 404) {
                User::where('id', $userId)->where('fcm_token', $token)->update(['fcm_token' => null]);
            } elseif ($response->failed()) {
                Log::warning('Push not sent', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::warning('Push not sent: ' . $e->getMessage());

            return null;
        }
    }

    /** The service account's key (project_id, client_email, private_key), or null when Firebase is not set up */
    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials');
        if (!$path || !is_file($path)) {
            return null;
        }
        $key = json_decode((string) file_get_contents($path), true);

        return isset($key['project_id'], $key['client_email'], $key['private_key']) ? $key : null;
    }

    /** Google's access token for sending (an hour; kept 50 minutes) */
    private static function accessToken(array $key): ?string
    {
        return Cache::remember('fcm.access_token', 3000, function () use ($key) {
            $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
                'iss' => $key['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (!openssl_sign($unsigned, $signature, $key['private_key'], 'sha256WithRSAEncryption')) {
                return null;
            }
            $response = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . $b64($signature),
            ]);

            return $response->json('access_token') ?: null;
        }) ?: null;
    }
}

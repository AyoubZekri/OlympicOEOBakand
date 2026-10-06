<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TokenCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_is_checked_once_then_read_from_the_file_and_logout_stops_it_at_once(): void
    {
        Cache::store('file')->flush();
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->withHeaders($headers)->getJson('/api/user')->assertOk();
        $this->app['auth']->forgetGuards(); // a new request, as on the server

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withHeaders($headers)->getJson('/api/user')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();
        $this->assertStringNotContainsString('personal_access_tokens', $queries); // not asked again
        $this->assertStringNotContainsString('update', strtolower($queries));     // and nothing written

        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/user')->assertUnauthorized();
    }
}

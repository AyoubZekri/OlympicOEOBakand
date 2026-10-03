<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertsVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_changes_when_something_is_added_edited_or_deleted(): void
    {
        Cache::forget('alerts.version.tables');
        $this->getJson('/api/alerts/version')->assertUnauthorized();

        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');

        $v0 = $version();
        $this->assertSame($v0, $version()); // nothing changed: same answer

        $session = TrainingSession::create(['team_id' => Team::create(['name' => 'أكابر'])->id, 'session_date' => '2026-10-05', 'location' => 'الملعب', 'start_time' => '17:00', 'end_time' => '18:30', 'status' => 'مجدولة']);
        $v1 = $version();
        $this->assertNotSame($v0, $v1);

        $this->travel(5)->seconds();
        $session->update(['location' => 'القاعة']);
        $v2 = $version();
        $this->assertNotSame($v1, $v2);

        $session->delete();
        $this->assertNotSame($v2, $version());
    }
}

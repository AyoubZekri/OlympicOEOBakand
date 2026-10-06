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
        Cache::flush();
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
        $v3 = $version();
        $this->assertNotSame($v2, $v3);

        // the newer tables are watched too (a medical notice)
        \App\Models\MedicalNotice::create(['record_id' => 1, 'player_id' => 1, 'kind' => 'deleted']);
        $this->assertNotSame($v3, $version());
    }

    public function test_a_reply_or_a_decision_on_a_disciplinary_action_changes_the_version(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');
        $me = \App\Models\Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $case = \App\Models\DisciplinaryCase::create(['individuals_id' => $me->id, 'incident_date' => '2026-10-01', 'description' => 'غياب', 'case_status' => 'مفتوح']);
        $action = \App\Models\DisciplinaryAction::create(['case_id' => $case->id, 'action_type' => 'طلب توضيح', 'action_date' => '2026-10-01']);
        $v0 = $version();

        $this->travel(2)->seconds();
        $this->actingAs($user)->postJson('/api/disciplinary/mine/reply', ['id' => $case->id, 'player_statements' => 'كنت مريضاً'])->assertOk();
        $v1 = $version();
        $this->assertNotSame($v0, $v1); // the member replied: the manager's alerts reload

        $this->travel(2)->seconds();
        $action->fresh()->update(['admin_notes' => 'قبول التبرير']);
        $this->assertNotSame($v1, $version()); // the decision: the member's alerts reload
    }

    public function test_a_table_created_after_the_list_was_cached_is_watched_soon_after(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');

        \Illuminate\Support\Facades\Schema::drop('medical_notices'); // not migrated yet
        $v0 = $version();

        (require database_path('migrations/2026_10_05_140000_create_medical_notices_table.php'))->up(); // migrated now
        $this->travel(6)->minutes();
        $v1 = $version();
        \App\Models\MedicalNotice::create(['record_id' => 1, 'player_id' => 1, 'kind' => 'deleted']);
        $this->assertNotSame($v1, $version());
    }
}

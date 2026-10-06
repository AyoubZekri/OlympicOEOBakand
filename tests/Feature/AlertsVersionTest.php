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
        // Public, and not a single database query
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->getJson('/api/alerts/version')->assertOk()->assertJsonStructure(['version']);
        $this->assertSame([], \Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

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

        // a member moved to another category (their sessions change)
        $this->travel(2)->seconds();
        $member = \App\Models\Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $v3 = $version();
        $this->travel(2)->seconds();
        $member->update(['team_id' => Team::create(['name' => 'أواسط'])->id]);
        $this->assertNotSame($v3, $v3 = $version());

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

    public function test_an_absence_its_justification_and_the_decision_change_the_version(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');
        $me = \App\Models\Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $v0 = $version();

        $absence = \App\Models\AppAbsence::create(['player_id' => $me->id, 'absence_type' => 'غائب غير مبرر', 'event_category' => 'تدريب', 'event_date' => now()->toDateString(), 'justification_status' => 'none']);
        $v1 = $version();
        $this->assertNotSame($v0, $v1); // recorded absent: the member is told

        $this->travel(2)->seconds();
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absence->id, 'text' => 'كنت مريضاً'])->assertOk();
        $v2 = $version();
        $this->assertNotSame($v1, $v2); // justified: the manager is told

        $this->travel(2)->seconds();
        $this->actingAs($user)->postJson('/api/absences/update-justification', ['id' => $absence->id, 'justification_status' => 'مرفوض', 'decision_note' => 'لا توجد وثيقة'])->assertOk();
        $this->assertNotSame($v2, $version()); // decided: the member is told
    }

    public function test_editing_a_match_report_changes_the_version(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');
        $coach = \App\Models\Individual::create(['type' => 'staff', 'first_name' => 'مدرب', 'last_name' => 'أول']);
        $match = \App\Models\Matchs::create(['competition' => 'البطولة', 'opponent' => 'النجم', 'match_date' => '2026-10-05 15:00', 'location' => 'الملعب',
            'coach_id' => $coach->id, 'admin_id' => $user->id, 'team_id' => Team::create(['name' => 'أكابر'])->id]);

        $this->actingAs($user)->postJson('/api/administrative-reports/save', ['match_id' => $match->id, 'refereeing_notes' => 'تحكيم جيد'])->assertOk();
        $v1 = $version();

        $this->travel(2)->seconds();
        $this->actingAs($user)->postJson('/api/administrative-reports/save', ['match_id' => $match->id, 'refereeing_notes' => 'تحكيم سيء'])->assertOk();
        $this->assertNotSame($v1, $version()); // the same report edited: seen at once
    }

    public function test_editing_a_meeting_decision_changes_the_version(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');
        $meeting = \App\Models\Meeting::create(['topic' => 'التحضير للموسم', 'date' => '2026-10-05', 'time' => '18:00', 'location' => 'مقر النادي']);
        $decision = \App\Models\Decision::create(['meeting_id' => $meeting->id, 'decision_text' => 'شراء معدات', 'progress' => 0]);
        $v1 = $version();

        $this->travel(2)->seconds();
        $this->actingAs($user)->putJson("/api/decisions/{$decision->id}", ['progress' => 50])->assertOk();
        $v2 = $version();
        $this->assertNotSame($v1, $v2); // progress / status / text edited: seen at once

        $this->travel(2)->seconds();
        $this->actingAs($user)->deleteJson("/api/decisions/{$decision->id}")->assertSuccessful();
        $this->assertNotSame($v2, $version());
    }

    public function test_editing_a_trip_or_a_medical_file_changes_the_version(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $version = fn () => $this->actingAs($user)->getJson('/api/alerts/version')->assertOk()->json('version');
        $travel = \App\Models\TravelItinerary::create(['destination' => 'وهران', 'departure_time' => now()->addDays(4)->setTime(8, 0)]);
        $me = \App\Models\Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $doctor = \App\Models\Individual::create(['type' => 'doctor', 'first_name' => 'يوسف', 'last_name' => 'قادري']);
        $record = \App\Models\PlayerMedicalRecord::create(['player_id' => $me->id, 'doctor_id' => $doctor->id, 'injury_date' => '2026-10-01', 'record_status' => 'مفتوح/مصاب']);
        $v1 = $version();

        $this->travel(2)->seconds();
        $travel->update(['departure_time' => now()->addDays(5)->setTime(9, 0)]);
        $v2 = $version();
        $this->assertNotSame($v1, $v2); // the trip moved

        $this->travel(2)->seconds();
        $this->actingAs($user)->postJson("/api/medical-records/update/{$record->id}", ['next_exam_date' => '2026-10-09'])->assertOk();
        $this->assertNotSame($v2, $version()); // the exam set
    }

    public function test_all_the_alerts_lists_come_in_one_request(): void
    {
        $this->getJson('/api/alerts/all')->assertUnauthorized();

        $user = User::factory()->create();
        $me = \App\Models\Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        \App\Models\AppAbsence::create(['player_id' => $me->id, 'absence_type' => 'غائب غير مبرر', 'event_category' => 'تدريب', 'event_date' => now()->toDateString(), 'justification_status' => 'none']);

        $paths = json_encode(['abs' => '/absences/mine', 'tasks' => '/tasks?scope=my', 'nope' => '/users']);
        $out = $this->actingAs($user)->getJson('/api/alerts/all?p=' . urlencode($paths))->assertOk()->json();

        // the same answer as the list's own route
        $this->assertSame(200, $out['abs']['status']);
        $this->assertSame($this->actingAs($user)->getJson('/api/absences/mine')->json(), $out['abs']['data']);
        $this->assertSame(200, $out['tasks']['status']);
        // only the alerts' lists
        $this->assertSame(404, $out['nope']['status']);
        $this->assertNull($out['nope']['data']);
    }
}

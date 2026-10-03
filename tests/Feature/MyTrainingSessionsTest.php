<?php

namespace Tests\Feature;

use App\Models\AppAbsence;
use App\Models\Individual;
use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTrainingSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(Team $team, string $date): TrainingSession
    {
        return TrainingSession::create([
            'team_id' => $team->id,
            'session_date' => $date,
            'location' => 'الملعب',
            'start_time' => '17:00',
            'end_time' => '18:30',
            'status' => 'مجدولة',
        ]);
    }

    public function test_a_user_sees_the_sessions_of_their_category_with_their_attendance(): void
    {
        $user = User::factory()->create();
        $mine = Team::create(['name' => 'أكابر']);
        $other = Team::create(['name' => 'أواسط']);
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id, 'team_id' => $mine->id]);
        $mate = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'team_id' => $mine->id]);

        $old = $this->makeSession($mine, '2026-09-20');
        $new = $this->makeSession($mine, '2026-10-05');
        $this->makeSession($other, '2026-10-04');

        AppAbsence::create(['player_id' => $me->id, 'training_session_id' => $old->id, 'absence_type' => 'متأخر', 'reason' => 'النقل']);
        AppAbsence::create(['player_id' => $mate->id, 'training_session_id' => $new->id, 'absence_type' => 'غائب مبرر']);

        $data = $this->actingAs($user)->getJson('/api/training-sessions/mine')->assertOk()->json();

        $this->assertCount(2, $data);
        $this->assertSame($new->id, $data[0]['id']); // newest first
        $this->assertSame('أكابر', $data[0]['team_name']);
        $this->assertNull($data[0]['my_absence']); // a teammate's absence is not mine
        $this->assertSame('متأخر', $data[1]['my_absence']);
        $this->assertSame('النقل', $data[1]['my_absence_note']);
    }

    public function test_a_user_without_a_category_sees_nothing_and_login_is_required(): void
    {
        $this->getJson('/api/training-sessions/mine')->assertUnauthorized();

        $user = User::factory()->create();
        Individual::create(['type' => 'staff', 'first_name' => 'سمير', 'last_name' => 'بن', 'user_id' => $user->id]);
        $this->makeSession(Team::create(['name' => 'أكابر']), '2026-10-05');

        $this->actingAs($user)->getJson('/api/training-sessions/mine')->assertOk()->assertJsonCount(0);
    }

    public function test_the_members_are_told_when_a_session_is_created_changed_cancelled_or_deleted(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3)->setTime(12, 0));
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $mine = Team::create(['name' => 'أكابر']);
        $other = Team::create(['name' => 'أواسط']);
        Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id, 'team_id' => $mine->id]);
        $notices = fn () => collect($this->actingAs($user)->getJson('/api/training-sessions/mine/notices')->assertOk()->json());
        $payload = ['team_id' => $mine->id, 'date' => '2026-10-05', 'location' => 'الملعب', 'start' => '17:00', 'end' => '18:30', 'status' => 'مجدولة'];

        // Created
        $this->actingAs($manager)->postJson('/api/training-sessions/create', $payload)->assertCreated();
        $id = TrainingSession::first()->id;
        $n = $notices();
        $this->assertCount(1, $n);
        $this->assertSame(['created', $id, 'أكابر', '2026-10-05', '17:00'], [$n[0]['kind'], $n[0]['session_id'], $n[0]['team_name'], $n[0]['date'], $n[0]['start']]);

        // Edited: new time, the old one is kept; only the latest notice per session
        $this->actingAs($manager)->postJson('/api/training-sessions/update', ['id' => $id, 'start' => '18:00', 'end' => '19:30'] + $payload)->assertOk();
        $n = $notices();
        $this->assertCount(1, $n);
        $this->assertSame('updated', $n[0]['kind']);
        $this->assertSame('18:00', $n[0]['start']);
        $this->assertSame('17:00', $n[0]['previous']['start']);

        // Same values again / a status step: nothing new
        $this->actingAs($manager)->postJson('/api/training-sessions/update', ['id' => $id, 'start' => '18:00', 'end' => '19:30'] + $payload)->assertOk();
        $this->actingAs($manager)->postJson('/api/training-sessions/update-status', ['id' => $id, 'status' => 'جارية'])->assertOk();
        $this->assertSame(2, \App\Models\TrainingSessionNotice::count());

        // Cancelled, then back on
        $this->actingAs($manager)->postJson('/api/training-sessions/update-status', ['id' => $id, 'status' => 'ملغاة'])->assertOk();
        $this->assertSame('cancelled', $notices()[0]['kind']);
        $this->actingAs($manager)->postJson('/api/training-sessions/update-status', ['id' => $id, 'status' => 'مجدولة'])->assertOk();
        $this->assertSame('restored', $notices()[0]['kind']);

        // Deleted: still announced
        $this->actingAs($manager)->postJson('/api/training-sessions/delete', ['id' => $id])->assertOk();
        $n = $notices();
        $this->assertCount(1, $n);
        $this->assertSame('deleted', $n[0]['kind']);
        $this->assertSame('2026-10-05', $n[0]['date']);

        // Another category's session: not mine; a session moved away from my category: cancelled for me
        $this->actingAs($manager)->postJson('/api/training-sessions/create', ['team_id' => $other->id] + $payload)->assertCreated();
        $this->assertCount(1, $notices());
        $this->actingAs($manager)->postJson('/api/training-sessions/create', ['date' => '2026-10-06'] + $payload)->assertCreated();
        $moved = TrainingSession::latest('id')->first()->id;
        $this->actingAs($manager)->postJson('/api/training-sessions/update', ['id' => $moved, 'team_id' => $other->id, 'date' => '2026-10-06'] + $payload)->assertOk();
        $this->assertSame('cancelled', $notices()->firstWhere('session_id', $moved)['kind']);

        // Once the session's day is over, it is no longer announced
        $this->travelTo(now()->setDate(2026, 10, 7));
        $this->assertCount(0, $notices());
    }
}

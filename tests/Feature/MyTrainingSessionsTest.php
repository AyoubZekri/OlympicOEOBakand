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
}

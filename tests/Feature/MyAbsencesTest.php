<?php

namespace Tests\Feature;

use App\Models\AppAbsence;
use App\Models\Individual;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAbsencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_justifies_and_requests_their_own_absences(): void
    {
        $this->getJson('/api/absences/mine')->assertUnauthorized();

        $user = User::factory()->create();
        $team = Team::create(['name' => 'أكابر']);
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id, 'team_id' => $team->id]);
        $mate = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'team_id' => $team->id]);

        $absent = AppAbsence::create(['player_id' => $me->id, 'absence_type' => 'غائب غير مبرر', 'event_category' => 'تدريب', 'event_date' => '2026-09-20', 'justification_status' => 'none']);
        AppAbsence::create(['player_id' => $me->id, 'absence_type' => 'متأخر', 'event_category' => 'مباراة', 'event_date' => '2026-09-25', 'justification_status' => 'accepted']);
        $theirs = AppAbsence::create(['player_id' => $mate->id, 'absence_type' => 'غياب', 'event_date' => '2026-09-22']);

        $data = $this->actingAs($user)->getJson('/api/absences/mine')->assertOk()->json();
        $this->assertCount(2, $data);
        $this->assertSame('متأخر', $data[0]['absence_type']); // newest first
        $this->assertSame('أكابر', $data[1]['team_name']);

        // Justify mine: waiting for a decision
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absent->id, 'text' => ''])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absent->id, 'text' => 'كنت مريضاً'])->assertOk();
        $absent->refresh();
        $this->assertSame(['pending', 'كنت مريضاً'], [$absent->justification_status, $absent->reason]);

        // Not someone else's, not one already accepted
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $theirs->id, 'text' => 'x'])->assertNotFound();
        $accepted = AppAbsence::where('justification_status', 'accepted')->first();
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $accepted->id, 'text' => 'x'])->assertStatus(422);

        // A holiday request over three days, and an absence announced in advance
        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'end_date' => '2026-10-12', 'reason' => 'سفر عائلي'])->assertCreated();
        $leave = AppAbsence::latest('id')->first();
        $this->assertSame([$me->id, 'طلب عطلة', 'pending', '3 أيام (حتى 2026-10-12)', 'طلب العضو'], [$leave->player_id, $leave->absence_type, $leave->justification_status, $leave->duration, $leave->record_source]);

        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'absence', 'event_date' => '2026-10-07', 'event_category' => 'مباراة', 'reason' => 'امتحان'])->assertCreated();
        $this->assertSame(['غياب', 'مباراة'], [AppAbsence::latest('id')->first()->absence_type, AppAbsence::latest('id')->first()->event_category]);

        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'end_date' => '2026-10-01', 'reason' => 'x'])->assertStatus(422);
        $this->assertCount(4, $this->actingAs($user)->getJson('/api/absences/mine')->json());

        // An account without a member cannot ask
        $this->actingAs(User::factory()->create())->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'reason' => 'x'])->assertStatus(422);
    }
}

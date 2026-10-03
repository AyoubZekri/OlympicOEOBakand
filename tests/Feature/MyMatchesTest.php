<?php

namespace Tests\Feature;

use App\Models\AppAbsence;
use App\Models\Individual;
use App\Models\MatchCallup;
use App\Models\MatchNotice;
use App\Models\Matchs;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MyMatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $manager;
    private Team $mine;
    private Team $other;
    private Individual $me;
    private Individual $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 3)->setTime(12, 0));
        $this->user = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->mine = Team::create(['name' => 'أكابر']);
        $this->other = Team::create(['name' => 'أواسط']);
        $this->me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $this->user->id, 'team_id' => $this->mine->id]);
        $this->coach = Individual::create(['type' => 'staff', 'first_name' => 'سمير', 'last_name' => 'بن']);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'competition' => 'البطولة', 'opponent' => 'النجم', 'match_date' => '2026-10-05 15:00',
            'location' => 'ملعب 5 جويلية', 'coach_id' => $this->coach->id, 'admin_id' => $this->manager->id, 'team_id' => $this->mine->id,
        ], $extra);
    }

    private function notices()
    {
        return collect($this->actingAs($this->user)->getJson('/api/matches/mine/notices')->assertOk()->json());
    }

    public function test_a_user_sees_the_matches_of_their_category_with_their_callup_and_attendance(): void
    {
        $this->getJson('/api/matches/mine')->assertUnauthorized();

        $called = Matchs::create($this->payload(['match_date' => '2026-09-20 15:00', 'team_score' => 2, 'opponent_score' => 1]));
        $notCalled = Matchs::create($this->payload());
        Matchs::create($this->payload(['team_id' => $this->other->id]));
        MatchCallup::create(['match_id' => $called->id, 'player_id' => $this->me->id, 'is_starter' => true, 'yellow_cards' => 1, 'rating' => 7.5]);
        DB::table('match_goals')->insert(['match_id' => $called->id, 'scorer_id' => $this->me->id, 'minute' => 30, 'created_at' => now(), 'updated_at' => now()]);
        AppAbsence::create(['player_id' => $this->me->id, 'match_id' => $called->id, 'absence_type' => 'متأخر']);

        $data = $this->actingAs($this->user)->getJson('/api/matches/mine')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertSame($notCalled->id, $data[0]['id']); // newest first
        $this->assertNull($data[0]['my_callup']);
        $this->assertSame('أكابر', $data[1]['team']['name']);
        $this->assertTrue($data[1]['my_callup']['is_starter']);
        $this->assertSame(1, $data[1]['my_callup']['goals']);
        $this->assertSame(1, $data[1]['my_callup']['yellow_cards']);
        $this->assertSame('متأخر', $data[1]['my_absence']);
    }

    public function test_the_members_are_told_when_a_match_is_scheduled_changed_postponed_cancelled_or_deleted(): void
    {
        // Scheduled
        $id = $this->actingAs($this->manager)->postJson('/api/matches/create', $this->payload())->assertCreated()->json('data.id');
        $n = $this->notices();
        $this->assertCount(1, $n);
        $this->assertSame(['created', $id, 'أكابر', '2026-10-05 15:00', 'النجم'], [$n[0]['kind'], $n[0]['match_id'], $n[0]['team_name'], $n[0]['match_date'], $n[0]['opponent']]);

        // New time: the old one is kept; the latest notice per match only
        $this->actingAs($this->manager)->postJson('/api/matches/update', $this->payload(['id' => $id, 'match_date' => '2026-10-05 17:00']))->assertOk();
        $n = $this->notices();
        $this->assertCount(1, $n);
        $this->assertSame('updated', $n[0]['kind']);
        $this->assertSame('2026-10-05 15:00', $n[0]['previous']['match_date']);

        // A result or the same values: nothing new
        $this->actingAs($this->manager)->postJson('/api/matches/update', $this->payload(['id' => $id, 'match_date' => '2026-10-05 17:00', 'team_score' => 1, 'opponent_score' => 0]))->assertOk();
        $this->assertSame(2, MatchNotice::count());

        // Postponed, cancelled, back on a new date
        $this->actingAs($this->manager)->postJson('/api/matches/update', $this->payload(['id' => $id, 'match_date' => '2026-10-05 17:00', 'match_status' => 'مؤجلة']))->assertOk();
        $this->assertSame('postponed', $this->notices()[0]['kind']);
        $this->actingAs($this->manager)->postJson('/api/matches/update', $this->payload(['id' => $id, 'match_date' => '2026-10-05 17:00', 'match_status' => 'ملغاة']))->assertOk();
        $this->assertSame('cancelled', $this->notices()[0]['kind']);
        $this->actingAs($this->manager)->postJson('/api/matches/update', $this->payload(['id' => $id, 'match_date' => '2026-10-08 16:00', 'match_status' => 'upcoming']))->assertOk();
        $n = $this->notices()[0];
        $this->assertSame(['restored', '2026-10-08 16:00'], [$n['kind'], $n['match_date']]);

        // Deleted: still announced
        $this->actingAs($this->manager)->postJson('/api/matches/delete', ['id' => $id])->assertOk();
        $n = $this->notices();
        $this->assertCount(1, $n);
        $this->assertSame('deleted', $n[0]['kind']);

        // Another category's match: not mine; moved away from my category: cancelled for me
        $this->actingAs($this->manager)->postJson('/api/matches/create', $this->payload(['team_id' => $this->other->id]))->assertCreated();
        $this->assertCount(1, $this->notices());
        $moved = $this->actingAs($this->manager)->postJson('/api/matches/create', $this->payload(['match_date' => '2026-10-06 15:00']))->json('data.id');
        $this->actingAs($this->manager)->postJson('/api/matches/update', $this->payload(['id' => $moved, 'match_date' => '2026-10-06 15:00', 'team_id' => $this->other->id]))->assertOk();
        $this->assertSame('cancelled', $this->notices()->firstWhere('match_id', $moved)['kind']);

        // Once the match day is over, it is no longer announced
        $this->travelTo(now()->setDate(2026, 10, 9));
        $this->assertCount(0, $this->notices());
    }

    public function test_the_managers_list_tells_what_is_still_to_do(): void
    {
        $match = Matchs::create($this->payload());
        $other = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'team_id' => $this->mine->id]);
        $row = fn () => collect($this->actingAs($this->manager)->getJson('/api/matches')->assertOk()->json('data'))->firstWhere('id', $match->id);

        $r = $row();
        $this->assertSame([0, 0, 0, 0, null], [$r['callups_count'], $r['starters_count'], $r['rated_count'], $r['reports_count'], $r['attendance_taken_at']]);

        MatchCallup::create(['match_id' => $match->id, 'player_id' => $this->me->id, 'is_starter' => true, 'rating' => 8]);
        MatchCallup::create(['match_id' => $match->id, 'player_id' => $other->id]);
        \App\Models\AdministrativeMatchReport::create(['match_id' => $match->id]);
        // Everyone present: no absence row, but the sheet is marked as taken
        $this->actingAs($this->manager)->postJson('/api/match-attendance/save', ['match_id' => $match->id, 'records' => [
            ['player_id' => $this->me->id, 'status' => 'حاضر'], ['player_id' => $other->id, 'status' => 'حاضر'],
        ]])->assertOk();

        $r = $row();
        $this->assertSame([2, 1, 1, 1], [$r['callups_count'], $r['starters_count'], $r['rated_count'], $r['reports_count']]);
        $this->assertNotNull($r['attendance_taken_at']);
    }

    public function test_a_training_sheet_taken_is_marked(): void
    {
        $session = \App\Models\TrainingSession::create(['team_id' => $this->mine->id, 'session_date' => '2026-10-03', 'location' => 'الملعب', 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'مكتملة']);
        $taken = fn () => collect($this->actingAs($this->manager)->getJson('/api/training-sessions')->json())->firstWhere('id', $session->id)['attendance_taken'];
        $this->assertFalse($taken());
        $this->actingAs($this->manager)->postJson('/api/training-attendance/save', ['session_id' => $session->id, 'records' => [
            ['player_id' => $this->me->id, 'status' => 'حاضر'],
        ]])->assertOk();
        $this->assertTrue($taken());
    }
}

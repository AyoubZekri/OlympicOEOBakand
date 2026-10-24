<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\PlayerEvaluation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BulkEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function evaluator(bool $evaluate = true): User
    {
        $role = Role::create(['name' => 'مدرب', 'type' => 'partial', 'permissions' => json_encode(['members' => ['view' => true, 'evaluate' => $evaluate]])]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function row(int $id, array $over = []): array
    {
        return $over + ['playerId' => $id, 'scoreDiscipline' => 8, 'scoreFitness' => 12, 'scoreTechnical' => 15, 'scoreTactical' => 10,
            'scoreMatchPerformance' => 14, 'scoreInstructions' => 7, 'scoreBehavior' => 9, 'recommendation' => 'استمرار عادي'];
    }

    public function test_a_group_evaluation_saves_every_active_player_or_none(): void
    {
        $a = Individual::create(['type' => 'player', 'first_name' => 'لاعب', 'last_name' => 'أول', 'status' => 'active']);
        $b = Individual::create(['type' => 'player', 'first_name' => 'لاعب', 'last_name' => 'ثان', 'status' => 'active']);
        $off = Individual::create(['type' => 'player', 'first_name' => 'لاعب', 'last_name' => 'موقوف', 'status' => 'inactive']);
        $staff = Individual::create(['type' => 'staff', 'first_name' => 'مدرب', 'last_name' => 'مساعد', 'status' => 'active']);
        $body = fn (array $items) => ['season' => '2026-2027', 'evaluationType' => 'شهري', 'periodStart' => '2026-10-01', 'periodEnd' => '2026-10-31', 'items' => $items];

        // Only who may evaluate
        $this->as($this->evaluator(false))->postJson('/api/player-evaluations/bulk', $body([$this->row($a->id)]))->assertForbidden();
        $coach = $this->evaluator();

        // A mark above its maximum, a player twice, a stopped player or a staff member: nothing saved
        $this->as($coach)->postJson('/api/player-evaluations/bulk', $body([$this->row($a->id, ['scoreDiscipline' => 11])]))->assertStatus(422);
        $this->as($coach)->postJson('/api/player-evaluations/bulk', $body([$this->row($a->id), $this->row($a->id)]))->assertStatus(422);
        $this->as($coach)->postJson('/api/player-evaluations/bulk', $body([$this->row($a->id), $this->row($off->id)]))->assertStatus(422);
        $this->as($coach)->postJson('/api/player-evaluations/bulk', $body([$this->row($staff->id)]))->assertStatus(422);
        $this->assertSame(0, PlayerEvaluation::count());

        // The active players: all saved, with the sheet's season, type and month, and their total
        $this->as($coach)->postJson('/api/player-evaluations/bulk', $body([$this->row($a->id), $this->row($b->id, ['scoreBehavior' => 10])]))
            ->assertCreated()->assertJsonPath('count', 2);
        $this->assertSame(2, PlayerEvaluation::count());
        $saved = PlayerEvaluation::where('player_id', $b->id)->first();
        $this->assertSame([76, 'شهري', '2026-2027'], [(int) $saved->total_score, $saved->evaluation_type, $saved->season]);

        // Each player's history: its own evaluations only
        $this->assertSame([(string) $b->id], array_column($this->as($coach)->getJson('/api/player-evaluations?member_id=' . $b->id)->assertOk()->json(), 'playerId'));
        $this->assertCount(2, $this->as($coach)->getJson('/api/player-evaluations')->json());
    }
}

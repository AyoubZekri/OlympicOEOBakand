<?php

namespace Tests\Feature;

use App\Models\GeneralGoal;
use App\Models\Individual;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class GeneralGoalTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function manager(array $goals): User
    {
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['goals' => $goals])]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_the_management_adds_edits_and_deletes_a_goal(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'admin', 'type' => 'full', 'permissions' => '{}'])->id]);
        $coach = Individual::create(['type' => 'coach', 'first_name' => 'سمير', 'last_name' => 'بلقاسم']);
        $player = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);

        // The goal, the member and the season are required; the season is two following years
        $this->as($admin)->postJson('/api/goals/create', [])->assertStatus(422)->assertJsonValidationErrors(['goal', 'individual_id', 'season']);
        $this->as($admin)->postJson('/api/goals/create', ['goal' => 'x', 'individual_id' => $coach->id, 'season' => '2026 - 2028'])
            ->assertStatus(422)->assertJsonValidationErrors('season');

        // Written 2026/2027, kept as "2026 - 2027"
        $id = $this->as($admin)->postJson('/api/goals/create', ['goal' => ' الصعود إلى القسم الأعلى ', 'individual_id' => $coach->id, 'season' => '2026/2027'])
            ->assertCreated()
            ->assertJsonPath('data.goal', 'الصعود إلى القسم الأعلى')
            ->assertJsonPath('data.season', '2026 - 2027')
            ->assertJsonPath('data.member.name', 'سمير بلقاسم')
            ->assertJsonPath('data.member.type', 'مدرب')
            ->json('data.id');

        $this->as($admin)->getJson('/api/goals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->as($admin)->getJson('/api/goals/options')->assertOk()
            ->assertJsonCount(2, 'data.members')->assertJsonPath('data.seasons', ['2026 - 2027']);

        $this->as($admin)->postJson('/api/goals/update', ['id' => $id, 'goal' => 'تسجيل 20 هدفاً', 'individual_id' => $player->id, 'season' => '2025-2026'])
            ->assertOk()->assertJsonPath('data.member.name', 'ياسين كريم')->assertJsonPath('data.season', '2025 - 2026');
        $this->assertDatabaseHas('general_goals', ['id' => $id, 'goal' => 'تسجيل 20 هدفاً', 'individual_id' => $player->id, 'created_by' => $admin->id]);

        // The goal stays when its member is deleted
        $player->delete();
        $this->as($admin)->getJson('/api/goals')->assertJsonPath('data.0.member', null);

        $this->as($admin)->postJson('/api/goals/delete', ['id' => $id])->assertOk();
        $this->assertSame(0, GeneralGoal::count());
    }

    public function test_each_action_needs_its_permission(): void
    {
        $member = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $goal = GeneralGoal::create(['goal' => 'هدف', 'individual_id' => $member->id, 'season' => '2026 - 2027']);
        $body = ['goal' => 'هدف', 'individual_id' => $member->id, 'season' => '2026 - 2027'];

        $this->as(User::factory()->create())->getJson('/api/goals')->assertForbidden();

        $viewer = $this->manager(['view' => true]);
        $this->as($viewer)->getJson('/api/goals')->assertOk()->assertJsonCount(1, 'data');
        $this->as($viewer)->getJson('/api/goals/options')->assertOk();
        $this->as($viewer)->postJson('/api/goals/create', $body)->assertForbidden();
        $this->as($viewer)->postJson('/api/goals/update', $body + ['id' => $goal->id])->assertForbidden();
        $this->as($viewer)->postJson('/api/goals/delete', ['id' => $goal->id])->assertForbidden();

        $editor = $this->manager(['view' => true, 'add' => true, 'edit' => true]);
        $this->as($editor)->postJson('/api/goals/create', $body)->assertCreated();
        $this->as($editor)->postJson('/api/goals/update', ['id' => $goal->id, 'goal' => 'هدف جديد'] + $body)->assertOk();
        $this->as($editor)->postJson('/api/goals/delete', ['id' => $goal->id])->assertForbidden();

        $this->as($this->manager(['delete' => true]))->postJson('/api/goals/delete', ['id' => $goal->id])->assertOk();
    }
}

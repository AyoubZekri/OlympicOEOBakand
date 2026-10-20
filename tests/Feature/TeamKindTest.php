<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_is_for_players_or_for_the_staff(): void
    {
        $user = User::factory()->create();

        // Not said: players
        $players = $this->actingAs($user)->postJson('/api/teams/create', ['name' => 'U19'])->assertCreated()->json('team.id');
        $this->assertSame('players', \App\Models\Team::find($players)->kind);

        $staff = $this->postJson('/api/teams/create', ['name' => 'الطاقم الفني', 'kind' => 'staff'])->assertCreated()->json('team.id');
        $this->postJson('/api/teams/create', ['name' => 'x', 'kind' => 'other'])->assertStatus(422);

        $this->postJson('/api/teams/update', ['id' => $players, 'kind' => 'staff'])->assertOk();
        $kinds = collect($this->getJson('/api/teams')->json())->pluck('kind', 'id');
        $this->assertSame('staff', $kinds[$players]);
        $this->assertSame('staff', $kinds[$staff]);
    }
}

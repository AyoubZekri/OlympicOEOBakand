<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleOptionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_is_saved_without_a_role_and_its_role_can_be_removed(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['matches' => ['view' => true]])]);

        // created without a role (the field left empty, as the forms send it)
        $this->actingAs($admin)->postJson('/api/users/create', ['name' => 'لاعب جديد', 'email' => 'player@test.dz', 'password' => 'secret12', 'role_id' => ''])
            ->assertCreated();
        $this->assertNull(User::where('email', 'player@test.dz')->value('role_id'));

        // a role given, then removed
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($admin)->postJson('/api/users/update', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'password' => '', 'role_id' => ''])
            ->assertOk();
        $this->assertNull($user->fresh()->role_id);

        // the account then tells it has no role (the app gives it the personal space only)
        $data = $this->actingAs($user->fresh())->getJson('/api/user')->assertOk()->json('data');
        $this->assertNull($data['role']);
    }
}

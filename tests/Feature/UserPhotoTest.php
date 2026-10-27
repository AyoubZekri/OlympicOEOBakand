<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_linked_to_a_member_comes_with_the_members_photo(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'مدير', 'type' => 'full'])->id]);
        $linked = User::factory()->create(['name' => 'أحمد علي']);
        Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $linked->id, 'photo' => 'photos/ahmed.jpg']);
        $alone = User::factory()->create(['name' => 'حساب بلا عضو']);

        $users = collect($this->actingAs($admin)->getJson('/api/users')->assertOk()->json())->keyBy('id');
        $this->assertSame('photos/ahmed.jpg', $users[$linked->id]['individual']['photo']);
        $this->assertSame('player', $users[$linked->id]['individual']['type']);
        $this->assertNull($users[$alone->id]['individual']);
    }
}

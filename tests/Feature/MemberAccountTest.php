<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'admin', 'type' => 'full', 'permissions' => '{}'])->id]);
    }

    private function createMember(array $extra = [])
    {
        return $this->actingAs($this->admin)->postJson('/api/individuals/create', $extra + [
            'type' => 'لاعب',
            'first_name' => 'أحمد',
            'last_name' => 'بن علي',
        ]);
    }

    public function test_a_member_without_email_gets_an_account_with_a_generated_email(): void
    {
        $response = $this->createMember()->assertCreated();
        $member = Individual::findOrFail($response->json('individual.id'));
        $user = User::findOrFail($member->user_id);

        $this->assertSame('أحمد بن علي', $user->name);
        $this->assertSame("member{$member->id}@members.olympic-oeo.local", $user->email);
        $this->assertNull($user->role_id);
        $this->assertSame($user->email, $response->json('account.email'));
        // The random password is returned once and works
        $this->assertTrue(Hash::check($response->json('account.password'), $user->password));
        $this->assertSame($member->id, $user->individual->id);
    }

    public function test_a_member_email_becomes_the_account_login(): void
    {
        $response = $this->createMember(['email' => 'ahmed@club.dz'])->assertCreated();
        $this->assertSame('ahmed@club.dz', User::findOrFail($response->json('individual.user_id'))->email);

        // Another account's email is refused, and nothing is created
        $before = Individual::count();
        $this->createMember(['email' => $this->admin->email])->assertStatus(422);
        $this->assertSame($before, Individual::count());
    }

    public function test_the_account_follows_the_member_and_stays_when_the_member_is_deleted(): void
    {
        $id = $this->createMember()->json('individual.id');
        $userId = Individual::find($id)->user_id;

        $this->actingAs($this->admin)->postJson('/api/individuals/update', ['id' => $id, 'first_name' => 'يوسف', 'email' => 'youcef@club.dz'])->assertOk();
        $user = User::find($userId);
        $this->assertSame('يوسف بن علي', $user->name);
        $this->assertSame('youcef@club.dz', $user->email);

        $this->actingAs($this->admin)->postJson('/api/individuals/update', ['id' => $id, 'email' => $this->admin->email])->assertStatus(422);

        $this->actingAs($this->admin)->postJson('/api/individuals/delete', ['id' => $id])->assertOk();
        $this->assertNotNull(User::find($userId));
    }

    public function test_existing_members_get_their_accounts_with_the_command(): void
    {
        $old = Individual::create(['type' => 'لاعب', 'first_name' => 'قديم', 'last_name' => 'عضو']);
        $this->assertNull($old->user_id);

        $this->artisan('members:create-accounts')->assertSuccessful();
        $this->assertNotNull($old->fresh()->user_id);
        $this->artisan('members:create-accounts')->expectsOutput('Created 0 member account(s).');
    }
}

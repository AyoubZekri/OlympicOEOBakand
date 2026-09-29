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

    public function test_editing_the_user_updates_its_member(): void
    {
        $id = $this->createMember()->json('individual.id');
        $userId = Individual::find($id)->user_id;

        $this->actingAs($this->admin)->postJson('/api/users/update', ['id' => $userId, 'name' => 'كريم بن يوسف زروقي', 'email' => 'karim@club.dz'])->assertOk();
        $member = Individual::find($id);
        $this->assertSame('كريم', $member->first_name);
        $this->assertSame('بن يوسف زروقي', $member->last_name);
        $this->assertSame('karim@club.dz', $member->email);

        // One word: only the first name changes
        $this->actingAs($this->admin)->postJson('/api/users/update', ['id' => $userId, 'name' => 'سمير'])->assertOk();
        $this->assertSame('سمير', Individual::find($id)->first_name);
        $this->assertSame('بن يوسف زروقي', Individual::find($id)->last_name);

        // And back: editing the member renames the account
        $this->actingAs($this->admin)->postJson('/api/individuals/update', ['id' => $id, 'last_name' => 'زروقي'])->assertOk();
        $this->assertSame('سمير زروقي', User::find($userId)->name);

        // A user that is not a member is unaffected
        $this->actingAs($this->admin)->postJson('/api/users/update', ['id' => $this->admin->id, 'name' => 'المدير'])->assertOk();
        $this->assertSame(1, Individual::count());
    }

    public function test_managers_see_and_regenerate_passwords(): void
    {
        $response = $this->createMember();
        $userId = $response->json('individual.user_id');
        $first = $response->json('account.password');

        // The member's random password can be shown later, to hand it over
        $this->actingAs($this->admin)->postJson('/api/users/password', ['id' => $userId])->assertOk()->assertJsonPath('password', $first);

        // A new one replaces it, and it is the one that logs in
        $new = $this->actingAs($this->admin)->postJson('/api/users/password/generate', ['id' => $userId])->assertOk()->json('password');
        $this->assertNotSame($first, $new);
        $this->assertTrue(Hash::check($new, User::find($userId)->password));
        $this->actingAs($this->admin)->postJson('/api/users/password', ['id' => $userId])->assertJsonPath('password', $new);

        // A password typed in the users page is kept too
        $this->actingAs($this->admin)->postJson('/api/users/update', ['id' => $userId, 'password' => 'Secret123'])->assertOk();
        $this->actingAs($this->admin)->postJson('/api/users/password', ['id' => $userId])->assertJsonPath('password', 'Secret123');
        $this->assertTrue(Hash::check('Secret123', User::find($userId)->password));

        // Never in the users list, and not for users without the permission
        $this->actingAs($this->admin)->getJson('/api/users')->assertOk()->assertJsonMissingPath('0.password_copy');
        $this->assertStringNotContainsString('password_copy', $this->actingAs($this->admin)->getJson('/api/users')->getContent());
        $plain = User::factory()->create(['role_id' => Role::create(['name' => 'staff', 'type' => 'custom', 'permissions' => json_encode(['usersAndRoles' => ['viewUsers' => true]])])->id]);
        $this->actingAs($plain)->postJson('/api/users/password', ['id' => $userId])->assertForbidden();
        $this->actingAs($plain)->postJson('/api/users/password/generate', ['id' => $userId])->assertForbidden();

        // Accounts made before copies were kept: unknown
        $this->actingAs($this->admin)->postJson('/api/users/password', ['id' => $this->admin->id])->assertJsonPath('password', null);
    }
}

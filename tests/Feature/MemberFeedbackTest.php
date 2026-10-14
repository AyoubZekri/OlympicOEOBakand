<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\MemberFeedback;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MemberFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function manager(array $feedback): User
    {
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['feedback' => $feedback])]);

        return User::factory()->create(['name' => 'المسير', 'role_id' => $role->id]);
    }

    public function test_a_member_sends_an_opinion_and_reads_the_reply(): void
    {
        $member = User::factory()->create();
        Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'user_id' => $member->id]);

        // Type and subject are required; the type is one of the three
        $this->as($member)->postJson('/api/feedback/mine/create', ['subject' => 'x'])->assertStatus(422);
        $this->as($member)->postJson('/api/feedback/mine/create', ['type' => 'other', 'subject' => 'x'])->assertStatus(422);
        $id = $this->as($member)->postJson('/api/feedback/mine/create', ['type' => 'complaint', 'subject' => 'الماء الساخن', 'body' => 'لا يوجد ماء ساخن في الحمامات'])
            ->assertCreated()->assertJsonPath('data.type', 'complaint')->json('data.id');

        // Replied by the management
        $manager = $this->manager(['view' => true, 'reply' => true]);
        $this->as($manager)->getJson('/api/feedback')->assertOk()
            ->assertJsonPath('data.0.member_name', 'ياسين كريم')->assertJsonPath('data.0.member_role', 'لاعب')->assertJsonPath('data.0.read', false);
        $this->as($manager)->postJson('/api/feedback/read', ['id' => $id])->assertOk();
        $this->as($manager)->postJson('/api/feedback/reply', ['id' => $id])->assertStatus(422);
        // A complaint: a reply, never a rating
        $this->as($manager)->postJson('/api/feedback/reply', ['id' => $id, 'reply' => 'تم إصلاح السخان', 'rating' => 4])
            ->assertOk()->assertJsonPath('data.rating', null)->assertJsonPath('data.replied_by', 'المسير');

        // The member reads it; then it can no longer be taken back
        $this->as($member)->getJson('/api/feedback/mine')->assertJsonPath('data.0.reply', 'تم إصلاح السخان')->assertJsonPath('data.0.reply_seen', false);
        $this->as($member)->postJson('/api/feedback/mine/seen')->assertOk();
        $this->as($member)->getJson('/api/feedback/mine')->assertJsonPath('data.0.reply_seen', true);
        $this->as($member)->postJson('/api/feedback/mine/delete', ['id' => $id])->assertStatus(422);
    }

    public function test_a_suggestion_is_rated_with_stars(): void
    {
        $member = User::factory()->create();
        $id = $this->as($member)->postJson('/api/feedback/mine/create', ['type' => 'suggestion', 'subject' => 'حصة استرجاع بعد المباراة'])->json('data.id');

        $manager = $this->manager(['view' => true, 'reply' => true]);
        $this->as($manager)->postJson('/api/feedback/reply', ['id' => $id, 'reply' => 'فكرة جيدة'])->assertStatus(422)->assertJsonValidationErrors('rating');
        $this->as($manager)->postJson('/api/feedback/reply', ['id' => $id, 'reply' => 'فكرة جيدة', 'rating' => 6])->assertStatus(422);
        $this->as($manager)->postJson('/api/feedback/reply', ['id' => $id, 'reply' => 'فكرة جيدة، سنطبقها', 'rating' => 5])->assertOk()->assertJsonPath('data.rating', 5);
        $this->assertSame(5, MemberFeedback::find($id)->rating);
    }

    public function test_only_its_author_sees_it_and_the_management_needs_the_permission(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->as($member)->postJson('/api/feedback/mine/create', ['type' => 'note', 'subject' => 'ملاحظة'])->json('data.id');

        $this->as($other)->getJson('/api/feedback/mine')->assertJsonCount(0, 'data');
        $this->as($other)->postJson('/api/feedback/mine/delete', ['id' => $id])->assertNotFound();

        // No role, or a role without the section: refused
        $this->as($other)->getJson('/api/feedback')->assertForbidden();
        $viewer = $this->manager(['view' => true]);
        $this->as($viewer)->getJson('/api/feedback')->assertOk()->assertJsonCount(1, 'data');
        $this->as($viewer)->postJson('/api/feedback/reply', ['id' => $id, 'reply' => 'ok'])->assertForbidden();
        $this->as($viewer)->postJson('/api/feedback/delete', ['id' => $id])->assertForbidden();

        // Taken back by its author while it has no reply
        $this->as($member)->postJson('/api/feedback/mine/delete', ['id' => $id])->assertOk();
        $this->assertSame(0, MemberFeedback::count());
    }

    public function test_the_alerts_get_the_opinions_in_their_batch(): void
    {
        $member = User::factory()->create();
        $this->as($member)->postJson('/api/feedback/mine/create', ['type' => 'complaint', 'subject' => 'الماء الساخن']);
        $manager = $this->manager(['view' => true]);

        $p = json_encode(['a' => '/feedback/mine', 'b' => '/feedback']);
        $this->as($member)->getJson('/api/alerts/all?p=' . urlencode($p))
            ->assertJsonPath('a.status', 200)->assertJsonPath('a.data.data.0.subject', 'الماء الساخن')
            ->assertJsonPath('b.status', 403);
        $this->as($manager)->getJson('/api/alerts/all?p=' . urlencode($p))
            ->assertJsonPath('b.status', 200)->assertJsonPath('b.data.data.0.read', false);
    }
}

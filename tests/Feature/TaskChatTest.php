<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TaskChatTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    public function test_an_open_chat_follows_the_conversation_cheaply_and_sees_when_it_is_read(): void
    {
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['tasks' => ['view' => true, 'add' => true, 'chat' => true]])]);
        $manager = User::factory()->create(['role_id' => $role->id]);
        $member = User::factory()->create();
        $id = $this->as($manager)->postJson('/api/tasks/create', ['title' => 'تحضير الملعب', 'kind' => 'urgent', 'nature' => 'internal', 'assignee_id' => $member->id])->json('data.id');

        // The chat's number: no login needed (nothing but the number), and it moves with a message
        Auth::forgetGuards();
        $v0 = $this->getJson("/api/tasks/chat-version?id={$id}")->assertOk()->json('version');
        $this->travel(1)->seconds();
        $first = $this->as($manager)->postJson('/api/tasks/messages/send', ['id' => $id, 'body' => 'أين وصلت؟'])->assertCreated()->json('data');
        $this->assertNotNull($first['ts']);
        Auth::forgetGuards();
        $v1 = $this->getJson("/api/tasks/chat-version?id={$id}")->json('version');
        $this->assertGreaterThan($v0, $v1);

        // Not read yet by the member: my message has one tick
        $this->assertNull($this->as($manager)->getJson("/api/tasks/messages?id={$id}")->json('seen_ts'));

        // The member reads it: the chat moves again, and my message is seen (✓✓)
        $this->travel(1)->seconds();
        $this->as($member)->getJson("/api/tasks/messages?id={$id}")->assertOk()->assertJsonCount(1, 'data');
        Auth::forgetGuards();
        $this->assertGreaterThan($v1, $this->getJson("/api/tasks/chat-version?id={$id}")->json('version'));
        $seen = $this->as($manager)->getJson("/api/tasks/messages?id={$id}")->json('seen_ts');
        $this->assertGreaterThanOrEqual($first['ts'], $seen);

        // Only what is new after the last message the chat has
        $this->travel(1)->seconds();
        $this->as($member)->postJson('/api/tasks/messages/send', ['id' => $id, 'body' => 'وصلت']);
        $new = $this->as($manager)->getJson("/api/tasks/messages?id={$id}&after={$first['id']}")->json('data');
        $this->assertSame(['وصلت'], array_column($new, 'body'));
    }

    public function test_who_has_the_chat_open_is_not_notified_of_its_messages(): void
    {
        $this->withoutDefer();
        config(['services.fcm.after_save' => false]);
        $key = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($key, json_encode(['project_id' => 'olympic-test', 'client_email' => 'push@olympic-test.iam.gserviceaccount.com', 'private_key' => 'test']));
        config(['services.fcm.credentials' => $key]);
        \Illuminate\Support\Facades\Cache::put('fcm.access_token', 'google-token', 3000);
        \Illuminate\Support\Facades\Http::fake(['fcm.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['name' => 'x'])]);
        $sent = fn () => collect(\Illuminate\Support\Facades\Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'fcm'))->count();

        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['tasks' => ['view' => true, 'add' => true, 'chat' => true]])]);
        $manager = User::factory()->create(['role_id' => $role->id, 'fcm_token' => 'manager-phone']);
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        $id = $this->as($manager)->postJson('/api/tasks/create', ['title' => 'تحضير الملعب', 'kind' => 'normal', 'nature' => 'internal', 'assignee_id' => $member->id])->json('data.id');
        $before = $sent();

        // The member has it open: the manager's message is not a notification for them
        Auth::forgetGuards();
        $this->getJson("/api/tasks/chat-version?id={$id}&me={$member->id}")->assertOk();
        $this->as($manager)->postJson('/api/tasks/messages/send', ['id' => $id, 'body' => 'أين وصلت؟'])->assertCreated();
        $this->assertSame($before, $sent());

        // Closed (no check for a while): notified again
        $this->travel(10)->seconds();
        $this->as($manager)->postJson('/api/tasks/messages/send', ['id' => $id, 'body' => 'رد عليّ'])->assertCreated();
        $this->assertSame($before + 1, $sent());
        @unlink($key);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        // Only what these tests send themselves (the alerts after a save: AlertPushTest)
        config(['services.fcm.after_save' => false]);
        // A test Firebase project's service account (no real key: Google's access token is already in hand)
        $this->key = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->key, json_encode(['project_id' => 'olympic-test', 'client_email' => 'push@olympic-test.iam.gserviceaccount.com', 'private_key' => 'test']));
        config(['services.fcm.credentials' => $this->key]);
        \Illuminate\Support\Facades\Cache::put('fcm.access_token', 'google-token', 3000);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/olympic-test/messages/1']),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->key);
        parent::tearDown();
    }

    private function as(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function manager(): User
    {
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['tasks' => ['view' => true, 'add' => true, 'edit' => true, 'changeStatus' => true, 'chat' => true]])]);

        return User::factory()->create(['name' => 'المسير', 'role_id' => $role->id]);
    }

    /** The notifications sent to FCM: [token, title, body, task_id] */
    private function sent(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
            ->map(fn ($pair) => [
                $pair[0]['message']['token'],
                $pair[0]['message']['notification']['title'],
                $pair[0]['message']['notification']['body'],
                ((array) $pair[0]['message']['data'])['task_id'] ?? null,
            ])->values()->all();
    }

    public function test_the_phone_gives_its_token_and_signing_out_takes_it_back(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->as($a)->postJson('/api/me/push-token', ['token' => 'phone-1'])->assertOk();
        $this->assertSame('phone-1', $a->fresh()->fcm_token);

        // The same phone signed in to another account: only that one
        $this->as($b)->postJson('/api/me/push-token', ['token' => 'phone-1'])->assertOk();
        $this->assertNull($a->fresh()->fcm_token);
        $this->assertSame('phone-1', $b->fresh()->fcm_token);

        $this->as($b)->postJson('/api/me/push-token', ['token' => null])->assertOk();
        $this->assertNull($b->fresh()->fcm_token);
    }

    public function test_a_task_its_messages_and_its_status_reach_the_phones(): void
    {
        $manager = $this->manager();
        $manager->forceFill(['fcm_token' => 'manager-phone'])->save();
        $member = User::factory()->create(['name' => 'ياسين', 'fcm_token' => 'member-phone']);

        $id = $this->as($manager)->postJson('/api/tasks/create', ['title' => 'اتصل بالحكم', 'kind' => 'urgent', 'nature' => 'call', 'assignee_id' => $member->id])
            ->assertCreated()->json('data.id');
        $this->assertSame([['member-phone', 'مهمة عاجلة', 'اتصل بالحكم', (string) $id]], $this->sent());

        // The member writes: who gave it is told (not the writer)
        $this->as($member)->postJson('/api/tasks/messages/send', ['id' => $id, 'body' => 'تم الاتصال'])->assertCreated();
        $this->assertSame(['manager-phone', 'رسالة من ياسين', 'تم الاتصال', (string) $id], $this->sent()[1]);

        // Its status: the member is told
        $this->as($manager)->postJson('/api/tasks/status', ['id' => $id, 'status' => 'done'])->assertOk();
        $this->assertSame(['member-phone', 'تغيّرت حالة المهمة', '«اتصل بالحكم»: منجزة', (string) $id], $this->sent()[2]);
        $this->assertCount(3, $this->sent());
    }

    public function test_an_appointment_task_is_told_once_when_its_alert_comes(): void
    {
        $this->travelTo(now('Africa/Algiers')->setTime(10, 0)->utc());
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        $now = TaskSchedule::localNow();
        $task = Task::create([
            'title' => 'تحضير أوراق المباراة', 'kind' => 'scheduled', 'nature' => 'internal', 'assignee_id' => $member->id,
            'alert_at' => $now->copy()->addHour(), 'due_at' => $now->copy()->addHours(5), 'status' => 'new', 'event_label' => '2026/10/28 15:00',
        ]);
        // An old one (before the notifications): only marked
        Task::create(['title' => 'قديمة', 'kind' => 'scheduled', 'nature' => 'internal', 'assignee_id' => $member->id, 'alert_at' => $now->copy()->subDays(3), 'status' => 'new']);

        TaskSchedule::pushArrived();
        $this->assertSame([], $this->sent());

        $this->travel(61)->minutes();
        TaskSchedule::pushArrived();
        TaskSchedule::pushArrived();
        $this->assertSame([['member-phone', 'موعد', 'تحضير أوراق المباراة · 2026/10/28 15:00', (string) $task->id]], $this->sent());
    }

    public function test_without_firebase_nothing_is_sent(): void
    {
        config(['services.fcm.credentials' => null]);
        $manager = $this->manager();
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        $this->as($manager)->postJson('/api/tasks/create', ['title' => 'مهمة', 'kind' => 'urgent', 'nature' => 'call', 'assignee_id' => $member->id])->assertCreated();
        $this->assertSame([], $this->sent());
    }
}

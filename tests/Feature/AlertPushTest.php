<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Alerts\AlertPusher;
use App\Services\TaskSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertPushTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->key, json_encode(['project_id' => 'olympic-test', 'client_email' => 'push@olympic-test.iam.gserviceaccount.com', 'private_key' => 'test']));
        config(['services.fcm.credentials' => $this->key]);
        Cache::put('fcm.access_token', 'google-token', 3000);
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/olympic-test/messages/1'])]);
        $this->travelTo(now('Africa/Algiers')->setTime(10, 0)->utc());
    }

    protected function tearDown(): void
    {
        @unlink($this->key);
        parent::tearDown();
    }

    /** The notifications sent: [token, title, link] */
    private function sent(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
            ->map(fn ($pair) => [$pair[0]['message']['token'], $pair[0]['message']['notification']['title'], ((array) $pair[0]['message']['data'])['link'] ?? null])
            ->values()->all();
    }

    private function lateTask(User $member, string $title): Task
    {
        $now = TaskSchedule::localNow();

        return Task::create([
            'title' => $title, 'kind' => 'normal', 'nature' => 'internal', 'assignee_id' => $member->id,
            'due_at' => $now->copy()->subHour(), 'status' => 'new', 'assignee_seen_at' => now(),
        ]);
    }

    public function test_each_new_alert_reaches_the_phone_once(): void
    {
        $manager = User::factory()->create(['fcm_token' => 'manager-phone', 'role_id' => Role::create(['name' => 'مدير', 'type' => 'full', 'permissions' => '{}'])->id]);
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        $this->lateTask($member, 'مهمة قديمة');

        // The first time: what is already there is only noted
        AlertPusher::run();
        $this->assertSame([], $this->sent());

        // A new late task: its member and the management are told, with its page
        $task = $this->lateTask($member, 'تحضير الملعب');
        AlertPusher::run();
        $sent = $this->sent();
        $this->assertContains(['member-phone', 'تأخرت مهمة', "/?task={$task->id}"], $sent);
        $this->assertContains(['manager-phone', 'مهمة متأخرة', "/?task={$task->id}"], $sent);
        $this->assertCount(2, $sent);

        // Never twice
        AlertPusher::run();
        $this->assertCount(2, $this->sent());
    }

    public function test_what_a_save_causes_is_sent_at_once_without_the_cron(): void
    {
        $this->withoutDefer();
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['tasks' => ['view' => true, 'add' => true, 'changeStatus' => true]])]);
        $manager = User::factory()->create(['role_id' => $role->id]);
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        $task = $this->lateTask($member, 'تحضير الملعب');
        // Already known to the phone
        AlertPusher::run();
        $this->assertCount(0, $this->sent());

        // A save in the tasks (another task's status): the tasks' alerts are looked at again, the member's phone at once
        Task::whereKey($task->id)->update(['due_at' => TaskSchedule::localNow()->subMinutes(5)]);
        $other = $this->lateTask(User::factory()->create(), 'أخرى');
        $this->actingAs($manager)->postJson('/api/tasks/status', ['id' => $other->id, 'status' => 'done'])->assertOk();
        $this->assertContains(['member-phone', 'تأخرت مهمة', "/?task={$task->id}"], $this->sent());
    }

    public function test_a_chat_message_does_not_run_the_alerts(): void
    {
        $this->withoutDefer();
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['tasks' => ['view' => true, 'chat' => true]])]);
        $manager = User::factory()->create(['role_id' => $role->id]);
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        $task = $this->lateTask($member, 'تحضير الملعب');
        AlertPusher::run();
        Task::whereKey($task->id)->update(['due_at' => TaskSchedule::localNow()->subMinutes(5)]);

        // Its own notification only (the message), not the alerts' run: the late task waits for the cron
        $this->actingAs($manager)->postJson('/api/tasks/messages/send', ['id' => $task->id, 'body' => 'أين وصلت؟'])->assertCreated();
        $this->assertNotContains(['member-phone', 'تأخرت مهمة', "/?task={$task->id}"], $this->sent());
    }

    public function test_an_absence_logged_on_a_member_reaches_their_phone_at_once(): void
    {
        $this->withoutDefer();
        $memberRole = Role::create(['name' => 'لاعب', 'type' => 'partial', 'permissions' => json_encode(['absences' => ['view' => true]])]);
        $user = User::factory()->create(['fcm_token' => 'member-phone', 'role_id' => $memberRole->id]);
        $me = \App\Models\Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $manager = User::factory()->create();
        AlertPusher::run();

        // The manager logs it: the member's phone at once (no cron), and it opens the member's absences
        $this->actingAs($manager)->postJson('/api/absences/create', ['player_id' => $me->id, 'absence_type' => 'غياب', 'event_category' => 'تدريب', 'event_date' => now('Africa/Algiers')->toDateString()])->assertCreated();
        $absence = \App\Models\AppAbsence::latest('id')->first();
        $this->assertSame([['member-phone', 'سُجل عليك غياب', "/my-absences?absence={$absence->id}"]], $this->sent());
    }

    public function test_many_new_alerts_come_as_a_few_and_one_summary(): void
    {
        $member = User::factory()->create(['fcm_token' => 'member-phone']);
        // The first run with nothing yet: the first alert after it is sent
        AlertPusher::run();
        $this->lateTask($member, 'أولى');
        AlertPusher::run();
        $this->assertCount(1, $this->sent());

        foreach (range(1, 6) as $i) {
            $this->lateTask($member, "مهمة {$i}");
        }
        AlertPusher::run();
        $sent = $this->sent();
        // 4, then one for the 2 others
        $this->assertCount(6, $sent);
        $this->assertSame(['member-phone', 'تنبيهات جديدة', '/'], end($sent));
    }
}

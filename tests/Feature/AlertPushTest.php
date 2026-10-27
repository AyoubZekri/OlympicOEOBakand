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

<?php

namespace Tests\Feature;

use App\Models\Matchs;
use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function manager(array $tasks = ['view' => true, 'add' => true, 'edit' => true, 'delete' => true]): User
    {
        $role = Role::create(['name' => 'مسير', 'type' => 'partial', 'permissions' => json_encode(['tasks' => $tasks])]);

        return User::factory()->create(['name' => 'المسير', 'role_id' => $role->id]);
    }

    private function task(array $over = []): array
    {
        return $over + ['title' => 'تحضير أوراق المباراة', 'kind' => 'normal', 'nature' => 'internal'];
    }

    public function test_a_task_is_given_followed_and_talked_about(): void
    {
        $this->travelTo(now('Africa/Algiers')->setTime(10, 0)->utc());
        $manager = $this->manager();
        $member = User::factory()->create(['name' => 'ياسين']);

        // Only who has the permission gives tasks; the member and the nature are required
        $this->as($member)->postJson('/api/tasks/create', $this->task(['assignee_id' => $member->id]))->assertForbidden();
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['nature' => 'other', 'assignee_id' => $member->id]))->assertStatus(422);
        $this->as($manager)->postJson('/api/tasks/create', $this->task())->assertStatus(422);
        // A normal task: no appointment fields
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['assignee_id' => $member->id, 'schedule' => 'event']))->assertStatus(422);

        // Urgent and voice notes: no deadline, only what to do, who and its nature
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'urgent', 'assignee_id' => $member->id, 'due_at' => '2026-12-01 12:00']))->assertStatus(422);
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'voice', 'assignee_id' => $member->id, 'due_at' => '2026-12-01 12:00']))->assertStatus(422);
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'voice', 'title' => 'اتصل بالحكم قبل المساء', 'assignee_id' => $member->id]))
            ->assertCreated()->assertJsonPath('data.kind', 'voice')->assertJsonPath('data.due_at', null);
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'urgent', 'assignee_id' => $member->id]))->assertCreated()->assertJsonPath('data.kind', 'urgent');
        Task::query()->delete();

        $id = $this->as($manager)->postJson('/api/tasks/create', $this->task(['nature' => 'call', 'assignee_id' => $member->id, 'due_at' => now('Africa/Algiers')->format('Y-m-d') . ' 12:00']))
            ->assertCreated()->assertJsonPath('data.status', 'new')->assertJsonPath('data.kind', 'normal')->assertJsonPath('data.overdue', false)->json('data.id');

        // The member sees it (new to them), and cannot change its status
        $this->as($member)->getJson('/api/tasks/mine')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.seen', false)->assertJsonPath('data.0.assignee.name', 'ياسين');
        $this->as($member)->postJson('/api/tasks/status', ['id' => $id, 'status' => 'done'])->assertForbidden();
        $this->as($member)->getJson('/api/tasks')->assertForbidden();

        // The conversation: the member writes, the managers see it unread until they open it
        $this->as($member)->getJson('/api/tasks/messages?id=' . $id)->assertOk();
        $this->as($member)->getJson('/api/tasks/mine')->assertJsonPath('data.0.seen', true);
        $this->travel(1)->minutes();
        $this->as($member)->postJson('/api/tasks/messages/send', ['id' => $id, 'body' => 'اتصلت بهم، ينتظرون الوثائق'])->assertCreated()->assertJsonPath('data.from_assignee', true);
        $this->as($manager)->getJson('/api/tasks')->assertJsonPath('data.0.unread', 1)->assertJsonPath('data.0.last_message.body', 'اتصلت بهم، ينتظرون الوثائق');
        $this->as($manager)->getJson('/api/tasks/messages?id=' . $id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.from_assignee', true);
        $this->as($manager)->getJson('/api/tasks')->assertJsonPath('data.0.unread', 0);

        // Someone else cannot read it
        $this->as(User::factory()->create())->getJson('/api/tasks/messages?id=' . $id)->assertForbidden();

        // The management changes the status: a reason for "on hold", written in the conversation (unread for the member)
        $this->travel(1)->minutes();
        $this->as($manager)->postJson('/api/tasks/status', ['id' => $id, 'status' => 'on_hold'])->assertStatus(422);
        $this->as($manager)->postJson('/api/tasks/status', ['id' => $id, 'status' => 'on_hold', 'note' => 'ننتظر الختم'])->assertOk()->assertJsonPath('data.status', 'on_hold');
        $this->as($member)->getJson('/api/tasks/mine')->assertJsonPath('data.0.unread', 1)->assertJsonPath('data.0.last_message.status', 'on_hold');

        // The deadline passes: overdue (not a status), until it is done
        $this->travel(3)->hours();
        $this->as($manager)->getJson('/api/tasks')->assertJsonPath('data.0.overdue', true)->assertJsonPath('data.0.status', 'on_hold');
        $this->as($manager)->postJson('/api/tasks/status', ['id' => $id, 'status' => 'done'])->assertOk()->assertJsonPath('data.overdue', false);

        // Deleted
        $this->as($manager)->postJson('/api/tasks/delete', ['id' => $id])->assertOk();
        $this->assertSame(0, Task::count());
    }

    public function test_an_appointment_linked_to_an_event_creates_a_task_for_every_new_event(): void
    {
        $manager = $this->manager();
        $member = User::factory()->create();

        // The deadline comes after the alert
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'scheduled', 'assignee_id' => $member->id, 'schedule' => 'event', 'event_type' => 'match', 'alert_offset' => -1440, 'due_offset' => -2880]))
            ->assertStatus(422)->assertJsonValidationErrors('due_offset');
        // Two days before: the alert; one day before: the deadline
        $template = $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'scheduled', 'assignee_id' => $member->id, 'schedule' => 'event', 'event_type' => 'match', 'alert_offset' => -2880, 'due_offset' => -1440]))
            ->assertCreated()->assertJsonPath('data.status', 'active')->json('data.id');
        $this->as($member)->getJson('/api/tasks/mine')->assertJsonCount(0, 'data');

        $match = Matchs::create(['competition' => 'البطولة', 'opponent' => 'النجم', 'match_date' => '2026-11-10 15:00', 'location' => 'الملعب',
            'coach_id' => \App\Models\Individual::create(['type' => 'staff', 'first_name' => 'مدرب', 'last_name' => 'أول'])->id,
            'admin_id' => $manager->id, 'team_id' => Team::create(['name' => 'أكابر'])->id]);

        $this->as($member)->getJson('/api/tasks/mine')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alert_at', '2026-11-08 15:00')->assertJsonPath('data.0.due_at', '2026-11-09 15:00')
            ->assertJsonPath('data.0.source', 'match')->assertJsonPath('data.0.event_label', 'مباراة ضد النجم · 2026/11/10 15:00');

        // The match moved: the dates follow
        $match->update(['match_date' => '2026-11-12 18:00']);
        $this->as($member)->getJson('/api/tasks/mine')->assertJsonPath('data.0.due_at', '2026-11-11 18:00');

        // Stopped: a new match creates nothing
        $this->as($manager)->postJson('/api/tasks/templates/toggle', ['id' => $template])->assertJsonPath('data.status', 'paused');
        Matchs::create($match->only(['competition', 'opponent', 'location', 'coach_id', 'admin_id', 'team_id']) + ['match_date' => '2026-11-20 15:00']);
        $this->assertSame(1, Task::where('template_id', $template)->count());
        $this->as($manager)->getJson('/api/tasks/templates')->assertJsonPath('data.0.tasks_count', 1);

        // The match deleted: its task is cancelled, said in the conversation
        $match->delete();
        $task = Task::where('template_id', $template)->first();
        $this->assertSame('cancelled', $task->status);
        $this->assertSame('cancelled', $task->messages()->first()->status);
    }

    public function test_a_periodic_appointment_creates_its_task_on_its_day(): void
    {
        $manager = $this->manager();
        $member = User::factory()->create();
        // Thursday 2026-10-22, 09:00 in Algeria
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-22 09:00', 'Africa/Algiers')->utc());

        // weekly: a weekday (1 → 7)
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'scheduled', 'assignee_id' => $member->id, 'schedule' => 'periodic', 'repeat' => 'weekly', 'repeat_day' => 8, 'alert_offset' => 480, 'due_offset' => 1080]))
            ->assertStatus(422);
        // Every Thursday: alert at 08:00, deadline at 18:00 — today's task at once
        $this->as($manager)->postJson('/api/tasks/create', $this->task(['kind' => 'scheduled', 'assignee_id' => $member->id, 'schedule' => 'periodic', 'repeat' => 'weekly', 'repeat_day' => 4, 'alert_offset' => 480, 'due_offset' => 1080]))
            ->assertCreated();
        $this->as($member)->getJson('/api/tasks/mine')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alert_at', '2026-10-22 08:00')->assertJsonPath('data.0.due_at', '2026-10-22 18:00')->assertJsonPath('data.0.source', 'periodic');

        // Checked again the same day: still one; a week later: the next one
        TaskSchedule::generateDue(true);
        $this->assertSame(1, Task::where('is_template', false)->count());
        $this->travel(7)->days();
        TaskSchedule::generateDue(true);
        $this->assertSame(2, Task::where('is_template', false)->count());

        // monthly on the 31st: the month's last day when it is shorter (November: the 30th)
        $template = Task::create(['title' => 'تقرير شهري', 'kind' => 'scheduled', 'nature' => 'internal', 'assignee_id' => $member->id, 'is_template' => true,
            'schedule' => 'periodic', 'repeat' => 'monthly', 'repeat_day' => 31, 'alert_offset' => 0, 'due_offset' => 1440, 'status' => 'active']);
        $this->assertTrue(TaskSchedule::isRepeatDay($template, \Illuminate\Support\Carbon::parse('2026-11-30')));
        $this->assertFalse(TaskSchedule::isRepeatDay($template, \Illuminate\Support\Carbon::parse('2026-10-30')));
    }
}

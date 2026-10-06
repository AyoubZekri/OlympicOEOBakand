<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Tasks\RecurrenceRule;
use App\Services\Tasks\TaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $worker;
    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $full = Role::create(['name' => 'admin', 'type' => 'full', 'permissions' => '{}']);
        $staff = Role::create(['name' => 'staff', 'type' => 'custom', 'permissions' => json_encode([
            '_v' => 2,
            'tasks' => ['view' => true, 'add' => false, 'review' => true],
        ])]);

        $this->manager = User::factory()->create(['role_id' => $full->id]);
        $this->worker = User::factory()->create(['role_id' => $staff->id]);
        $this->reviewer = User::factory()->create(['role_id' => $staff->id]);
    }

    private function createTask(array $extra = []): array
    {
        return $this->actingAs($this->manager)->postJson('/api/tasks/create', $extra + [
            'title' => 'تحضير أرضية الملعب',
            'assignee_id' => $this->worker->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'requires_approval' => true,
        ])->assertCreated()->json('data');
    }

    private function act(User $user, int $id, string $action, array $extra = [])
    {
        return $this->actingAs($user)->postJson('/api/tasks/action', ['id' => $id, 'action' => $action] + $extra);
    }

    public function test_full_workflow_with_review_and_history(): void
    {
        $task = $this->createTask();
        $this->assertSame('assigned', $task['status']);
        $this->assertMatchesRegularExpression('/^TSK-\d{4}-0001$/', $task['reference']);

        $this->act($this->worker, $task['id'], 'start')->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->act($this->worker, $task['id'], 'block', ['reason' => 'financial'])->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->act($this->worker, $task['id'], 'resume')->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->act($this->worker, $task['id'], 'submit')->assertOk()->assertJsonPath('data.status', 'in_review');
        $this->act($this->reviewer, $task['id'], 'return', ['reason' => 'الصور غير واضحة'])->assertOk()
            ->assertJsonPath('data.status', 'returned')->assertJsonPath('data.return_reason', 'الصور غير واضحة');
        $this->act($this->worker, $task['id'], 'start')->assertOk();
        $this->act($this->worker, $task['id'], 'submit')->assertOk();
        $this->act($this->reviewer, $task['id'], 'approve')->assertOk()->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewer_id', $this->reviewer->id);

        $history = $this->actingAs($this->worker)->getJson("/api/tasks/{$task['id']}")->assertOk()->json('data.history');
        $this->assertSame(
            ['created', 'start', 'block', 'resume', 'submit', 'return', 'start', 'submit', 'approve'],
            array_column($history, 'action')
        );
    }

    public function test_only_the_right_people_can_act(): void
    {
        $task = $this->createTask();

        // Changing the status is open to anyone who can see the task (here the creator);
        // nobody reviews their own task, even with the review permission
        $this->act($this->manager, $task['id'], 'start')->assertOk();
        $this->act($this->worker, $task['id'], 'submit')->assertOk();
        $this->act($this->worker, $task['id'], 'approve')->assertForbidden();

        // Without the review permission
        $plain = User::factory()->create(['role_id' => Role::create(['name' => 'plain', 'type' => 'custom', 'permissions' => json_encode(['_v' => 2, 'tasks' => ['view' => true]])])->id]);
        $this->act($plain, $task['id'], 'approve')->assertForbidden();
        // Someone unrelated to the task cannot change it
        $this->act($plain, $task['id'], 'block', ['reason' => 'financial'])->assertForbidden();
        $this->actingAs($plain)->getJson('/api/tasks?scope=review')->assertForbidden();

        // Wrong status
        $this->act($this->worker, $task['id'], 'block', ['reason' => 'financial'])->assertStatus(422);

        // Return needs a reason
        $this->act($this->reviewer, $task['id'], 'return')->assertStatus(422);

        // A user without tasks.add cannot create tasks
        $this->actingAs($this->worker)->postJson('/api/tasks/create', [
            'title' => 'x', 'assignee_id' => $this->reviewer->id, 'requires_approval' => false,
        ])->assertForbidden();

        // Someone outside the task, without the review permission, cannot open it
        $this->actingAs($plain)->getJson("/api/tasks/{$task['id']}")->assertForbidden();
    }

    public function test_block_reason_other_needs_a_note(): void
    {
        $task = $this->createTask();
        $this->act($this->worker, $task['id'], 'start');
        $this->act($this->worker, $task['id'], 'block', ['reason' => 'nope'])->assertStatus(422);
        $this->act($this->worker, $task['id'], 'block', ['reason' => 'other'])->assertStatus(422);
        $this->act($this->worker, $task['id'], 'block', ['reason' => 'other', 'note' => 'الملعب مغلق'])->assertOk()
            ->assertJsonPath('data.block_note', 'الملعب مغلق');
    }

    public function test_proof_is_required_before_submit(): void
    {
        $task = $this->createTask(['requires_proof' => true]);
        $this->act($this->worker, $task['id'], 'start');
        $this->act($this->worker, $task['id'], 'submit')->assertStatus(422);

        $this->actingAs($this->worker)->post('/api/tasks/attachments/create', [
            'id' => $task['id'], 'type' => 'file', 'file' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->act($this->worker, $task['id'], 'submit')->assertOk()->assertJsonPath('data.status', 'in_review');

        // No more attachments once sent for review
        $this->actingAs($this->worker)->postJson('/api/tasks/attachments/create', [
            'id' => $task['id'], 'type' => 'text', 'body' => 'متأخر',
        ])->assertStatus(422);
    }

    public function test_task_without_approval_is_done_on_submit(): void
    {
        $task = $this->createTask(['requires_approval' => false]);
        $this->act($this->worker, $task['id'], 'start');
        $this->act($this->worker, $task['id'], 'submit')->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_any_reviewer_sees_waiting_tasks_and_becomes_the_reviewer(): void
    {
        $task = $this->createTask();
        $this->assertNull($task['reviewer_id']);
        $this->act($this->worker, $task['id'], 'start');

        // Not waiting for review yet
        $this->actingAs($this->reviewer)->getJson('/api/tasks?scope=review')->assertJsonCount(0, 'data');
        $this->actingAs($this->reviewer)->getJson("/api/tasks/{$task['id']}")->assertForbidden();

        $this->act($this->worker, $task['id'], 'submit');
        $this->actingAs($this->reviewer)->getJson('/api/tasks?scope=review')->assertJsonCount(1, 'data');
        $this->actingAs($this->reviewer)->getJson("/api/tasks/{$task['id']}")->assertOk();
        // The assignee's own tasks are not in their review list
        $this->actingAs($this->worker)->getJson('/api/tasks?scope=review')->assertJsonCount(0, 'data');

        $this->act($this->manager, $task['id'], 'return', ['reason' => 'ناقص'])->assertOk()
            ->assertJsonPath('data.reviewer_id', $this->manager->id);
        // Still listed for the one who reviewed it
        $this->actingAs($this->manager)->getJson('/api/tasks?scope=review')->assertJsonCount(1, 'data');
        $this->actingAs($this->reviewer)->getJson('/api/tasks?scope=review')->assertJsonCount(0, 'data');
    }

    public function test_scopes_delete_for_good_and_stats(): void
    {
        $task = $this->createTask(['due_at' => now()->subHour()->toDateTimeString()]);
        $this->createTask(['due_at' => now()->subHours(2)->toDateTimeString()]);
        $this->createTask();

        $this->actingAs($this->worker)->getJson('/api/tasks?scope=my')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.is_overdue', true);
        $this->actingAs($this->worker)->getJson('/api/tasks?scope=all')->assertForbidden();

        // Deleted for good: no archive, nothing to restore, its history gone with it
        $this->actingAs($this->manager)->postJson('/api/tasks/delete', ['id' => $task['id']])->assertOk();
        $this->actingAs($this->manager)->getJson('/api/tasks?scope=all')->assertJsonCount(2, 'data');
        $this->actingAs($this->manager)->getJson('/api/tasks?scope=archive')->assertJsonCount(0, 'data');
        $this->assertDatabaseMissing('tasks', ['id' => $task['id']]);
        $this->assertDatabaseMissing('task_status_history', ['task_id' => $task['id']]);
        $this->actingAs($this->manager)->postJson('/api/tasks/restore', ['id' => $task['id']])->assertNotFound();

        $stats = $this->actingAs($this->manager)->getJson('/api/tasks/stats')->assertOk()->json('data');
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['overdue']);
        $this->assertNull($stats['on_time_rate']);
    }

    public function test_on_time_rate(): void
    {
        $late = $this->createTask(['requires_approval' => false, 'due_at' => now()->subDay()->toDateTimeString()]);
        $onTime = $this->createTask(['requires_approval' => false]);
        foreach ([$late, $onTime] as $t) {
            $this->act($this->worker, $t['id'], 'start');
            $this->act($this->worker, $t['id'], 'submit');
        }
        $this->actingAs($this->manager)->getJson('/api/tasks/stats')->assertJsonPath('data.on_time_rate', 50);
    }

    public function test_recurrence_rule(): void
    {
        $anchor = CarbonImmutable::parse('2026-09-28 00:00'); // Monday
        $weekly = new RecurrenceRule('FREQ=WEEKLY;BYDAY=MO,TH;BYHOUR=9;BYMINUTE=30', $anchor);
        $this->assertSame('2026-09-28 09:30', $weekly->nextAfter($anchor)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 09:30', $weekly->nextAfter(CarbonImmutable::parse('2026-09-28 10:00'))->format('Y-m-d H:i'));

        $monthly = new RecurrenceRule('FREQ=MONTHLY;BYMONTHDAY=31;BYHOUR=8', $anchor);
        $this->assertSame('2026-09-30 08:00', $monthly->nextAfter($anchor)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-31 08:00', $monthly->nextAfter(CarbonImmutable::parse('2026-10-01'))->format('Y-m-d H:i'));

        $until = new RecurrenceRule('FREQ=DAILY;BYHOUR=8;UNTIL=20260929', $anchor);
        $this->assertNull($until->nextAfter(CarbonImmutable::parse('2026-09-29 09:00')));

        $this->assertFalse(RecurrenceRule::isValid('FREQ=YEARLY'));
        $this->assertFalse(RecurrenceRule::isValid('FREQ=WEEKLY;BYDAY=XX'));
    }

    public function test_a_periodic_task_creates_only_its_next_occurrence(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 07:00'));
        // Saving creates the next occurrence (today 08:00), and only that one
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/create', [
            'title' => 'تقرير يومي', 'kind' => 'periodic', 'assignee_id' => $this->worker->id,
            'requires_approval' => false, 'rrule' => 'FREQ=DAILY;BYHOUR=8;BYMINUTE=0', 'duration_minutes' => 120,
        ])->assertCreated()->assertJsonPath('data.next_run_at', '2026-09-29 08:00');
        $this->assertSame(1, Task::count());
        // While it has not started, the next one is not created
        $this->assertSame(0, TaskGenerator::runPeriodic(CarbonImmutable::now()));

        // Once its time has come, the next one (tomorrow) is created, and nothing more
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:30'));
        $this->assertSame(1, TaskGenerator::runPeriodic(CarbonImmutable::now()));
        $this->assertSame(0, TaskGenerator::runPeriodic(CarbonImmutable::now()));
        $this->assertSame('2026-09-29 08:00', Task::orderByDesc('starts_at')->first()->starts_at->format('Y-m-d H:i'));

        // After a long stop, missed occurrences are skipped: only the next one is created
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
        $this->assertSame(1, TaskGenerator::runPeriodic(CarbonImmutable::now()));
        $this->assertSame(3, Task::count());
        $this->assertSame('2026-10-05 08:00', Task::orderByDesc('starts_at')->first()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-05 10:00', Task::orderByDesc('starts_at')->first()->due_at->format('Y-m-d H:i'));

        $this->artisan('tasks:generate-periodic')->assertSuccessful();
    }

    public function test_the_base_periodic_task_is_listed_edited_stopped_and_deleted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 07:00'));
        $template = $this->actingAs($this->manager)->postJson('/api/tasks/templates/create', [
            'title' => 'تقرير', 'kind' => 'periodic', 'assignee_id' => $this->worker->id,
            'requires_approval' => false, 'rrule' => 'FREQ=DAILY;BYHOUR=8;BYMINUTE=0', 'duration_minutes' => 120,
        ])->json('data');
        $first = Task::first();

        // The assignee sees the base task (without managing it); a stranger does not
        $this->actingAs($this->worker)->getJson('/api/tasks/templates')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.can_manage', false);
        $plain = User::factory()->create(['role_id' => Role::create(['name' => 'p', 'type' => 'custom', 'permissions' => json_encode(['_v' => 2, 'tasks' => ['view' => true]])])->id]);
        $this->actingAs($plain)->getJson('/api/tasks/templates')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->worker)->postJson('/api/tasks/templates/toggle', ['id' => $template['id'], 'active' => false])->assertForbidden();
        $this->actingAs($this->worker)->postJson('/api/tasks/templates/update', ['id' => $template['id']] + $template)->assertForbidden();

        // The occurrence shows the base task it comes from
        $this->actingAs($this->worker)->getJson("/api/tasks/{$first->id}")->assertJsonPath('data.template.id', $template['id']);

        // Stopping withdraws the waiting occurrence
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/toggle', ['id' => $template['id'], 'active' => false])
            ->assertOk()->assertJsonPath('withdrawn', 1)->assertJsonPath('data.active', false);
        $this->assertSame(0, Task::count());
        $this->assertSame(0, TaskGenerator::runPeriodic(CarbonImmutable::now()->addDays(5)));

        // Restarting creates the next occurrence again (withdrawn ones are not brought back)
        $this->travelTo(CarbonImmutable::parse('2026-09-28 09:00'));
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/toggle', ['id' => $template['id'], 'active' => true])->assertOk();
        $this->assertSame('2026-09-29 08:00', Task::first()->starts_at->format('Y-m-d H:i'));

        // Editing the base task (its title) is used by the next occurrences
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/update', ['id' => $template['id'], 'title' => 'تقرير العتاد'] + $template)->assertOk();
        $this->assertSame('تقرير العتاد', \App\Models\TaskTemplate::first()->title);

        // Deleting it withdraws the waiting occurrence
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/delete', ['id' => $template['id']])->assertOk()->assertJsonPath('withdrawn', 1);
        $this->assertSame(0, \App\Models\TaskTemplate::count());
    }

    public function test_event_templates_create_tasks_once_per_event(): void
    {
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/create', [
            'title' => 'تجهيز {event}', 'kind' => 'event', 'trigger' => 'match.created',
            'assignee_id' => $this->worker->id,
            'offset_minutes' => -120, 'duration_minutes' => 60,
        ])->assertCreated();
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/create', [
            'title' => 'x', 'kind' => 'event', 'trigger' => 'unknown', 'assignee_id' => $this->worker->id,
            'requires_approval' => false,
        ])->assertStatus(422);

        $kickoff = CarbonImmutable::now()->addDays(3)->setTime(16, 0);
        $this->assertSame(1, TaskGenerator::fromEvent('match.created', 'match:7', $kickoff, 'مباراة ضد النجم'));
        $this->assertSame(0, TaskGenerator::fromEvent('match.created', 'match:7', $kickoff, 'مباراة ضد النجم'));
        $this->assertSame(0, TaskGenerator::fromEvent('training.created', 'training:1', $kickoff, 'حصة'));

        $task = Task::first();
        $this->assertSame('تجهيز مباراة ضد النجم', $task->title);
        $this->assertSame('event', $task->source_type);
        $this->assertSame($kickoff->subHours(2)->format('Y-m-d H:i'), $task->due_at->format('Y-m-d H:i'));
        $this->assertSame($kickoff->subHours(3)->format('Y-m-d H:i'), $task->starts_at->format('Y-m-d H:i'));
        $this->assertNull($task->reviewer_id);
        $this->assertTrue($task->requires_approval);

        // Deleted: gone from every list, and not made a second time for the same match
        $this->actingAs($this->manager)->postJson('/api/tasks/delete', ['id' => $task->id])->assertOk();
        $this->actingAs($this->manager)->getJson('/api/tasks?scope=all')->assertJsonCount(0, 'data');
        $this->assertSame(0, TaskGenerator::fromEvent('match.created', 'match:7', $kickoff, 'مباراة ضد النجم'));
    }

    public function test_task_linked_to_an_upcoming_match_or_training(): void
    {
        $past = \App\Models\Matchs::create(['opponent' => 'القديم', 'match_date' => now()->subDays(3)]);
        $match = \App\Models\Matchs::create(['opponent' => 'النجم', 'match_date' => now()->addDays(2)->setTime(16, 0)]);
        $training = \App\Models\TrainingSession::create(['session_date' => now()->addDay()->toDateString(), 'start_time' => '18:30', 'status' => 'مجدولة']);
        \App\Models\TrainingSession::create(['session_date' => now()->addDay()->toDateString(), 'status' => 'ملغاة']);

        $matches = $this->actingAs($this->manager)->getJson('/api/tasks/events?type=match')->assertOk()->json('data');
        $this->assertSame([$match->id], array_column($matches, 'id'));
        $this->assertSame('مباراة ضد النجم', $matches[0]['title']);

        $trainings = $this->actingAs($this->manager)->getJson('/api/tasks/events?type=training')->assertOk()->json('data');
        $this->assertSame([$training->id], array_column($trainings, 'id'));
        $this->assertStringEndsWith('18:30', $trainings[0]['at']);

        $task = $this->createTask(['event_type' => 'match', 'event_id' => $match->id]);
        $this->assertSame('event', $task['source_type']);
        $this->assertSame("match:{$match->id}", $task['source_ref']);
        $this->actingAs($this->worker)->getJson("/api/tasks/{$task['id']}")->assertJsonPath('data.event.title', 'مباراة ضد النجم');

        $this->actingAs($this->manager)->postJson('/api/tasks/create', [
            'title' => 'x', 'assignee_id' => $this->worker->id, 'event_type' => 'training', 'event_id' => 999,
        ])->assertStatus(422);
        $this->assertNotNull($past);
    }

    public function test_default_list_follows_the_signed_in_user(): void
    {
        $mine = $this->createTask();
        $other = $this->createTask(['assignee_id' => $this->reviewer->id]);

        // Manager: everything
        $this->actingAs($this->manager)->getJson('/api/tasks')->assertJsonCount(2, 'data');
        // Worker: only the task assigned to them
        $this->actingAs($this->worker)->getJson('/api/tasks')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine['id']);

        // Once sent for review, a reviewer sees it next to their own task
        $this->act($this->worker, $mine['id'], 'start');
        $this->act($this->worker, $mine['id'], 'submit');
        $ids = array_column($this->actingAs($this->reviewer)->getJson('/api/tasks')->json('data'), 'id');
        sort($ids);
        $this->assertSame([$mine['id'], $other['id']], $ids);
    }

    public function test_tasks_linked_to_a_meeting_or_a_travel(): void
    {
        $meeting = \App\Models\Meeting::create(['topic' => 'تحضير الموسم', 'date' => now()->addDays(2)->toDateString(), 'time' => '10:00', 'location' => 'المقر']);
        \App\Models\Meeting::create(['topic' => 'قديم', 'date' => now()->subDays(3)->toDateString()]);
        $travel = \App\Models\TravelItinerary::create(['destination' => 'وهران', 'departure_time' => now()->addDays(4)->setTime(8, 0)]);

        $meetings = $this->actingAs($this->manager)->getJson('/api/tasks/events?type=meeting')->assertOk()->json('data');
        $this->assertSame([$meeting->id], array_column($meetings, 'id'));
        $this->assertSame('اجتماع: تحضير الموسم', $meetings[0]['title']);
        $this->assertStringEndsWith('10:00', $meetings[0]['at']);

        $travels = $this->actingAs($this->manager)->getJson('/api/tasks/events?type=travel')->assertOk()->json('data');
        $this->assertSame('تنقل إلى وهران', $travels[0]['title']);

        $task = $this->createTask(['event_type' => 'travel', 'event_id' => $travel->id]);
        $this->assertSame("travel:{$travel->id}", $task['source_ref']);
        $this->actingAs($this->worker)->getJson("/api/tasks/{$task['id']}")->assertJsonPath('data.event.title', 'تنقل إلى وهران');
        $this->createTask(['event_type' => 'meeting', 'event_id' => $meeting->id]);

        // Automatic tasks with every new travel
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/create', [
            'title' => 'تجهيز {event}', 'kind' => 'event', 'trigger' => 'travel.created', 'assignee_id' => $this->worker->id,
            'requires_approval' => false, 'offset_minutes' => -1440, 'duration_minutes' => 600,
        ])->assertCreated();
        \App\Models\TravelItinerary::create(['destination' => 'سطيف', 'departure_time' => now()->addDays(6)->setTime(7, 0)]);
        $this->assertTrue(\App\Models\Task::where('title', 'تجهيز تنقل إلى سطيف')->exists());
    }
}

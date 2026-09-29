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

        // Only the assignee starts; nobody reviews their own task, even with the review permission
        $this->act($this->reviewer, $task['id'], 'start')->assertForbidden();
        $this->act($this->worker, $task['id'], 'start')->assertOk();
        $this->act($this->worker, $task['id'], 'submit')->assertOk();
        $this->act($this->worker, $task['id'], 'approve')->assertForbidden();

        // Without the review permission
        $plain = User::factory()->create(['role_id' => Role::create(['name' => 'plain', 'type' => 'custom', 'permissions' => json_encode(['_v' => 2, 'tasks' => ['view' => true]])])->id]);
        $this->act($plain, $task['id'], 'approve')->assertForbidden();
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

    public function test_scopes_archive_and_stats(): void
    {
        $task = $this->createTask(['due_at' => now()->subHour()->toDateTimeString()]);
        $this->createTask();

        $this->actingAs($this->worker)->getJson('/api/tasks?scope=my')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_overdue', true);
        $this->actingAs($this->worker)->getJson('/api/tasks?scope=all')->assertForbidden();

        $this->actingAs($this->manager)->postJson('/api/tasks/delete', ['id' => $task['id']])->assertOk();
        $this->actingAs($this->manager)->getJson('/api/tasks?scope=all')->assertJsonCount(1, 'data');
        $this->actingAs($this->manager)->getJson('/api/tasks?scope=archive')->assertJsonCount(1, 'data');
        $this->actingAs($this->manager)->postJson('/api/tasks/restore', ['id' => $task['id']])->assertOk();

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

    public function test_periodic_templates_create_each_occurrence_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 07:00'));
        $this->actingAs($this->manager)->postJson('/api/tasks/templates/create', [
            'title' => 'تقرير يومي', 'kind' => 'periodic', 'assignee_id' => $this->worker->id,
            'requires_approval' => false, 'rrule' => 'FREQ=DAILY;BYHOUR=8;BYMINUTE=0', 'duration_minutes' => 120,
        ])->assertCreated()->assertJsonPath('data.next_run_at', '2026-09-28 08:00');

        $this->travelTo(CarbonImmutable::parse('2026-09-30 09:00'));
        $this->assertSame(3, TaskGenerator::runPeriodic(CarbonImmutable::now()));
        $this->assertSame(0, TaskGenerator::runPeriodic(CarbonImmutable::now()));

        $task = Task::orderBy('starts_at')->first();
        $this->assertSame('2026-09-28 08:00', $task->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-28 10:00', $task->due_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 08:00', TaskTemplate::first()->next_run_at->format('Y-m-d H:i'));

        $this->artisan('tasks:generate-periodic')->assertSuccessful();
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
    }
}

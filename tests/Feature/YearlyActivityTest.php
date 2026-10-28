<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearlyActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_yearly_activity_makes_its_one_task_and_moves_it_with_its_date(): void
    {
        $this->travelTo(now('Africa/Algiers')->setDate(2026, 10, 28)->setTime(10, 0)->utc());
        $role = Role::create(['name' => 'مواعيد', 'type' => 'partial', 'permissions' => json_encode([
            'appointments' => ['view' => true, 'viewUpcoming' => true, 'add' => true, 'edit' => true, 'toggle' => true],
        ])]);
        $manager = User::factory()->create(['role_id' => $role->id]);
        $member = User::factory()->create();
        $base = ['title' => 'الجمعية العامة السنوية', 'kind' => 'scheduled', 'nature' => 'internal', 'assignee_id' => $member->id, 'schedule' => 'once', 'alert_offset' => -10080, 'due_offset' => 0];

        // Its date is needed
        $this->actingAs($manager)->postJson('/api/tasks/create', $base)->assertStatus(422)->assertJsonValidationErrors('once_at');

        $id = $this->actingAs($manager)->postJson('/api/tasks/create', $base + ['once_at' => '2026-12-20 18:00'])
            ->assertCreated()->assertJsonPath('data.schedule', 'once')->assertJsonPath('data.once_at', '2026-12-20 18:00')->json('data.id');

        // One task at once: its alert a week before, its deadline at the activity; still to come
        $task = Task::where('template_id', $id)->sole();
        $this->assertSame(['2026-12-13 18:00', '2026-12-20 18:00', '2026/12/20 18:00'], [$task->alert_at->format('Y-m-d H:i'), $task->due_at->format('Y-m-d H:i'), $task->event_label]);
        $upcoming = $this->actingAs($manager)->getJson('/api/tasks/upcoming')->assertOk()->json('data');
        $this->assertSame(['once'], array_column($upcoming, 'source'));

        // Moved: the same task follows (never a second one)
        $this->actingAs($manager)->postJson('/api/tasks/update', $base + ['id' => $id, 'once_at' => '2027-01-10 09:00'])->assertOk();
        $this->assertSame(1, Task::where('template_id', $id)->count());
        $this->assertSame('2027-01-03 09:00', $task->fresh()->alert_at->format('Y-m-d H:i'));
    }
}

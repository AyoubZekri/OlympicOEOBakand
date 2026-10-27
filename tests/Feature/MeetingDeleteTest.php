<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_meeting_with_decisions_is_deleted_with_them(): void
    {
        $manager = User::factory()->create(['role_id' => Role::create(['name' => 'مدير', 'type' => 'full'])->id]);
        $meeting = Meeting::create(['topic' => 'التحضير للموسم', 'date' => '2026-09-05', 'time' => '18:00', 'location' => 'مقر النادي']);
        $other = Meeting::create(['topic' => 'اجتماع آخر', 'date' => '2026-09-06', 'time' => '10:00', 'location' => 'x']);
        Decision::create(['meeting_id' => $meeting->id, 'decision_text' => 'شراء كرات جديدة']);
        Decision::create(['meeting_id' => $meeting->id, 'decision_text' => 'تجديد الملعب']);
        Decision::create(['meeting_id' => $other->id, 'decision_text' => 'قرار آخر']);

        // The list says how many each has (the page warns before deleting)
        $listed = collect($this->actingAs($manager)->getJson('/api/meetings')->assertOk()->json())->keyBy('id');
        $this->assertSame([2, 1], [$listed[$meeting->id]['decisions_count'], $listed[$other->id]['decisions_count']]);

        $this->actingAs($manager)->deleteJson("/api/meetings/{$meeting->id}")->assertNoContent();
        $this->assertNull(Meeting::find($meeting->id));
        $this->assertSame(0, Decision::where('meeting_id', $meeting->id)->count());
        $this->assertSame(1, Decision::where('meeting_id', $other->id)->count());
    }
}

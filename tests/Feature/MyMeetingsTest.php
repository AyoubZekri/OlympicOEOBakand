<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\Individual;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyMeetingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_meetings_and_the_points_sent_into_the_meeting_list(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-04 10:00', 'Africa/Algiers'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $me = Individual::create(['type' => 'coach', 'first_name' => 'سمير', 'last_name' => 'بن', 'user_id' => $user->id]);
        $mate = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'user_id' => $other->id]);
        $outsider = User::factory()->create();
        Individual::create(['type' => 'player', 'first_name' => 'كريم', 'last_name' => 'زروقي', 'user_id' => $outsider->id]);

        $meeting = Meeting::create(['topic' => 'التحضير للموسم', 'date' => '2026-10-05', 'time' => '18:00', 'location' => 'مقر النادي',
            'attendees' => [['id' => (string) $me->id, 'name' => 'سمير بن', 'status' => 'confirmed'], ['id' => (string) $mate->id, 'name' => 'ياسين كريم']],
            'points' => ['ميزانية الموسم']]);
        \DB::table('meeting_attendees')->insert([['meeting_id' => $meeting->id, 'member_id' => $me->id], ['meeting_id' => $meeting->id, 'member_id' => $mate->id]]);
        Meeting::create(['topic' => 'اجتماع آخر', 'date' => '2026-10-06', 'time' => '10:00', 'location' => 'x']);
        Decision::create(['meeting_id' => $meeting->id, 'decision_text' => 'تجهيز الملعب', 'category' => 'تنظيمي', 'assignee_ids' => [(string) $me->id], 'deadline' => '2026-10-10', 'progress' => 20,
            'checklist_items' => [['id' => 'a', 'text' => 'قص العشب', 'checked' => true], ['id' => 'b', 'text' => 'طلاء الخطوط', 'checked' => false]]]);

        // Sending points: the invited members, not the others
        $this->getJson('/api/meetings/mine')->assertUnauthorized();
        $this->actingAs($user)->postJson("/api/meetings/{$meeting->id}/points", ['text' => ''])->assertStatus(422);
        $this->actingAs($user)->postJson("/api/meetings/{$meeting->id}/points", ['text' => 'برنامج التحضير البدني'])->assertCreated()->assertJsonPath('point.author', 'سمير بن');
        $this->actingAs($other)->postJson("/api/meetings/{$meeting->id}/points", ['text' => 'تجديد الأقمصة'])->assertCreated();
        $this->actingAs($outsider)->postJson("/api/meetings/{$meeting->id}/points", ['text' => 'x'])->assertForbidden();

        // The points are in the meeting's own list: the administration's text first, then the sent ones with their senders
        $stored = $meeting->fresh()->points;
        $this->assertSame('ميزانية الموسم', $stored[0]);
        $this->assertSame(['برنامج التحضير البدني', 'سمير بن', $user->id], [$stored[1]['text'], $stored[1]['author'], $stored[1]['user_id']]);
        $this->assertSame('ياسين كريم', $stored[2]['author']);
        // The management page reads them in the meetings list
        $listed = collect($this->getJson('/api/meetings')->json())->firstWhere('id', $meeting->id)['points'];
        $this->assertCount(3, $listed);

        $data = $this->actingAs($user)->getJson('/api/meetings/mine')->assertOk()->json();
        $this->assertCount(1, $data);
        $m = $data[0];
        $this->assertSame(['التحضير للموسم', '2026-10-05', '18:00', 'confirmed', true], [$m['topic'], $m['date'], $m['time'], $m['my_status'], $m['can_propose']]);
        $this->assertSame([['ميزانية الموسم', null, false], ['برنامج التحضير البدني', 'سمير بن', true], ['تجديد الأقمصة', 'ياسين كريم', false]],
            collect($m['points'])->map(fn ($p) => [$p['text'], $p['author'], $p['mine']])->all());
        $this->assertSame(['تجهيز الملعب', ['سمير بن'], true, 20], [$m['decisions'][0]['text'], $m['decisions'][0]['assignees'], $m['decisions'][0]['mine'], $m['decisions'][0]['progress']]);
        $this->assertSame([['text' => 'قص العشب', 'checked' => true], ['text' => 'طلاء الخطوط', 'checked' => false]], $m['decisions'][0]['checklist_items']);

        // Only the sender removes their point
        $theirs = $m['points'][2]['id'];
        $mine = $m['points'][1]['id'];
        $this->actingAs($user)->postJson('/api/meetings/points/delete', ['meeting_id' => $meeting->id, 'point_id' => $theirs])->assertForbidden();

        // Once the meeting has started: no new point, no removal by the sender
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-05 18:00', 'Africa/Algiers'));
        $this->actingAs($user)->postJson("/api/meetings/{$meeting->id}/points", ['text' => 'متأخرة'])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/meetings/points/delete', ['meeting_id' => $meeting->id, 'point_id' => $mine])->assertStatus(422);
        $this->assertFalse($this->actingAs($user)->getJson('/api/meetings/mine')->json()[0]['can_propose']);

        // A manager of the meetings removes any sent point
        $manager = User::factory()->create(['role_id' => Role::create(['name' => 'مدير', 'type' => 'full'])->id]);
        $this->actingAs($manager)->postJson('/api/meetings/points/delete', ['meeting_id' => $meeting->id, 'point_id' => $theirs])->assertOk();
        $this->assertCount(2, $meeting->fresh()->points);
    }
}

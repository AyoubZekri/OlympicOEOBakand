<?php

namespace Tests\Feature;

use App\Models\AppAbsence;
use App\Models\Individual;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAbsencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_justifies_and_requests_their_own_absences(): void
    {
        $this->getJson('/api/absences/mine')->assertUnauthorized();

        $user = User::factory()->create();
        $team = Team::create(['name' => 'أكابر']);
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id, 'team_id' => $team->id]);
        $mate = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'team_id' => $team->id]);

        $absent = AppAbsence::create(['player_id' => $me->id, 'absence_type' => 'غائب غير مبرر', 'event_category' => 'تدريب', 'event_date' => '2026-09-20', 'justification_status' => 'none']);
        AppAbsence::create(['player_id' => $me->id, 'absence_type' => 'متأخر', 'event_category' => 'مباراة', 'event_date' => '2026-09-25', 'justification_status' => 'accepted']);
        $theirs = AppAbsence::create(['player_id' => $mate->id, 'absence_type' => 'غياب', 'event_date' => '2026-09-22']);

        $data = $this->actingAs($user)->getJson('/api/absences/mine')->assertOk()->json();
        $this->assertCount(2, $data);
        $this->assertSame('متأخر', $data[0]['absence_type']); // newest first
        $this->assertSame('أكابر', $data[1]['team_name']);

        // Justify mine: waiting for a decision
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absent->id, 'text' => ''])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absent->id, 'text' => 'كنت مريضاً'])->assertOk();
        $absent->refresh();
        $this->assertSame(['pending', 'كنت مريضاً'], [$absent->justification_status, $absent->reason]);

        // Not someone else's, not one already accepted
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $theirs->id, 'text' => 'x'])->assertNotFound();
        $accepted = AppAbsence::where('justification_status', 'accepted')->first();
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $accepted->id, 'text' => 'x'])->assertStatus(422);

        // A holiday request over three days, and an absence announced in advance
        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'end_date' => '2026-10-12', 'reason' => 'سفر عائلي'])->assertCreated();
        $leave = AppAbsence::latest('id')->first();
        $this->assertSame([$me->id, 'طلب عطلة', 'pending', '3 أيام (حتى 2026-10-12)', 'طلب العضو'], [$leave->player_id, $leave->absence_type, $leave->justification_status, $leave->duration, $leave->record_source]);

        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'absence', 'event_date' => '2026-10-07', 'event_category' => 'مباراة', 'reason' => 'امتحان'])->assertCreated();
        $this->assertSame(['غياب', 'مباراة'], [AppAbsence::latest('id')->first()->absence_type, AppAbsence::latest('id')->first()->event_category]);

        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'end_date' => '2026-10-01', 'reason' => 'x'])->assertStatus(422);
        $this->assertCount(4, $this->actingAs($user)->getJson('/api/absences/mine')->json());

        // An account without a member cannot ask
        $this->actingAs(User::factory()->create())->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'reason' => 'x'])->assertStatus(422);
    }

    public function test_a_justification_text_or_document_within_24_hours_then_a_decision(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(10, 0));
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);

        // The manager logs an absence: the member has 24 hours
        $this->actingAs($manager)->postJson('/api/absences/create', ['player_id' => $me->id, 'absence_type' => 'غياب', 'event_category' => 'تدريب', 'event_date' => '2026-10-04'])->assertCreated();
        $absence = AppAbsence::latest('id')->first();
        $row = $this->actingAs($user)->getJson('/api/absences/mine')->json()[0];
        $this->assertSame('2026-10-05T10:00:00', substr($row['justify_until'], 0, 19));

        // Nothing sent: refused
        $this->actingAs($user)->post('/api/absences/mine/justify', ['id' => $absence->id], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($user)->post('/api/absences/mine/justify', ['id' => $absence->id, 'document' => \Illuminate\Http\UploadedFile::fake()->create('x.exe', 10)], ['Accept' => 'application/json'])->assertStatus(422);

        // A document alone is enough
        $this->travel(5)->hours();
        $this->actingAs($user)->post('/api/absences/mine/justify', [
            'id' => $absence->id,
            'document' => \Illuminate\Http\UploadedFile::fake()->create('certificat.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $absence->refresh();
        $this->assertSame('pending', $absence->justification_status);
        $this->assertStringContainsString('uploads/absences/absence_' . $absence->id . '_', $absence->attachment_path);
        $this->assertSame($absence->attachment_path, $this->actingAs($user)->getJson('/api/absences/mine')->json()[0]['attachment_url']);
        @unlink(public_path('uploads/absences/' . basename($absence->attachment_path)));

        // Waiting for the decision: no second justification
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absence->id, 'text' => 'نص'])->assertStatus(422);

        // Refused: may justify again while the 24 hours last
        $this->actingAs($manager)->postJson('/api/absences/update-justification', ['id' => $absence->id, 'justification_status' => 'rejected'])->assertOk();
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absence->id, 'text' => 'شهادة طبية مرفقة'])->assertOk();
        $this->actingAs($manager)->postJson('/api/absences/update-justification', ['id' => $absence->id, 'justification_status' => 'rejected'])->assertOk();

        // After 24 hours: too late
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 1));
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $absence->id, 'text' => 'متأخر'])->assertStatus(422)->assertJsonPath('message', 'انتهت مهلة تقديم التبرير (24 ساعة بعد تسجيل الغياب)');

        // A holiday request is not justified
        $this->actingAs($user)->postJson('/api/absences/mine/request', ['kind' => 'leave', 'event_date' => '2026-10-10', 'reason' => 'سفر'])->assertCreated();
        $leave = AppAbsence::latest('id')->first();
        $this->assertNull($this->actingAs($user)->getJson('/api/absences/mine')->json()[0]['justify_until'] ?? null);
        $this->actingAs($manager)->postJson('/api/absences/update-justification', ['id' => $leave->id, 'justification_status' => 'rejected'])->assertOk();
        $this->actingAs($user)->postJson('/api/absences/mine/justify', ['id' => $leave->id, 'text' => 'x'])->assertStatus(422);
    }
}

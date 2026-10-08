<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HearingMinutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hearing_keeps_its_officer_and_closing_time(): void
    {
        $user = User::factory()->create();
        $player = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $hearing = [
            'memberId' => $player->id, 'actionType' => 'استدعاء جلسة', 'incidentDate' => '2026-10-05',
            'reason' => 'شجار في التدريب', 'status' => 'مفتوح', 'presentPeople' => 'المدرب، المدير الرياضي',
            'deadlineOrHearingDate' => '2026-10-08 10:30',
        ];
        $this->actingAs($user)->postJson('/api/disciplinary/create', $hearing)->assertCreated();
        $id = $this->actingAs($user)->getJson('/api/disciplinary')->json('0.id');

        // The hearing held: statements, notes, its officer and when it closed
        $this->actingAs($user)->postJson('/api/disciplinary/update', $hearing + [
            'id' => $id, 'player_statements' => 'اعتذر عن الشجار', 'admin_notes' => 'إنذار شفهي',
            'hearingOfficer' => 'المدير الرياضي', 'hearingEndTime' => '11:15',
        ])->assertOk();

        $row = $this->actingAs($user)->getJson('/api/disciplinary')->json('0');
        $this->assertSame(['المدير الرياضي', '11:15', '10:30', '2026-10-08'],
            [$row['hearingOfficer'], $row['hearingEndTime'], $row['hearingTime'], $row['deadlineOrHearingDate']]);

        // The incident's time of day too
        $this->actingAs($user)->postJson('/api/disciplinary/update', array_merge($hearing, ['id' => $id, 'incidentDate' => '2026-10-05 17:45']))->assertOk();
        $row = $this->actingAs($user)->getJson('/api/disciplinary')->json('0');
        $this->assertSame(['2026-10-05', '17:45'], [$row['incidentDate'], $row['incidentTime']]);

        // A wrong time is refused
        $this->actingAs($user)->postJson('/api/disciplinary/update', $hearing + ['id' => $id, 'hearingEndTime' => '25:00'])->assertStatus(422);
    }

    public function test_the_officer_sees_the_hearings_they_run_until_closed(): void
    {
        $admin = User::factory()->create();
        $officerUser = User::factory()->create();
        Individual::create(['type' => 'admin', 'first_name' => 'سمير', 'last_name' => 'بلحاج', 'user_id' => $officerUser->id]);
        $player = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $hearing = [
            'memberId' => $player->id, 'actionType' => 'استدعاء جلسة', 'incidentDate' => '2026-10-05',
            'reason' => 'شجار في التدريب', 'status' => 'مفتوح', 'deadlineOrHearingDate' => '2026-10-08 10:30',
            'hearingOfficer' => '  سمير   بلحاج ',
        ];
        $this->actingAs($admin)->postJson('/api/disciplinary/create', $hearing)->assertCreated();
        $id = $this->actingAs($admin)->getJson('/api/disciplinary')->json('0.id');

        $this->app['auth']->forgetGuards();
        // Named (spaces do not matter): the hearing is in their list, with its time
        $row = $this->actingAs($officerUser)->getJson('/api/disciplinary/officiating')->assertOk()->json('data.0');
        $this->assertSame([$id, 'ياسين كريم', '2026-10-08', '10:30'], [$row['id'], $row['memberName'], $row['deadlineOrHearingDate'], $row['hearingTime']]);
        // Someone else runs none
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->getJson('/api/disciplinary/officiating')->assertJsonCount(0, 'data');

        // Closed: no longer in the list
        $this->actingAs($admin)->postJson('/api/disciplinary/update', $hearing + ['id' => $id, 'hearingEndTime' => '11:15'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs($officerUser)->getJson('/api/disciplinary/officiating')->assertJsonCount(0, 'data');
    }
}

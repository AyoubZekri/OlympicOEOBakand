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

        // A wrong time is refused
        $this->actingAs($user)->postJson('/api/disciplinary/update', $hearing + ['id' => $id, 'hearingEndTime' => '25:00'])->assertStatus(422);
    }
}

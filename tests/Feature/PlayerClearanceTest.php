<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerClearanceTest extends TestCase
{
    use RefreshDatabase;

    private function player(): Individual
    {
        return Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'status' => 'active']);
    }

    public function test_the_three_steps_of_a_departure(): void
    {
        $user = User::factory()->create(['name' => 'مسؤول العتاد']);
        $player = $this->player();
        $url = "/api/player-clearance/{$player->id}";

        // Step 1: date and reason are required
        $this->actingAs($user)->postJson('/api/player-clearance', ['player_id' => $player->id])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/player-clearance', ['player_id' => $player->id, 'exit_date' => '2026-10-20', 'exit_reason' => 'انتقال'])->assertOk();
        $this->actingAs($user)->getJson('/api/player-clearances')
            ->assertJsonPath('data.0.player_id', $player->id)->assertJsonPath('data.0.signed', 0)->assertJsonPath('data.0.closed', false);

        // Step 2: equipment not returned is pending: no signature without a note
        $equipmentId = DB::table('equipments')->insertGetId(['name' => 'قميص رسمي']);
        $operationId = DB::table('equipment_operations')->insertGetId(['member_id' => $player->id]);
        DB::table('equipment_movements')->insert(['operation_id' => $operationId, 'equipment_id' => $equipmentId, 'quantity' => 2]);
        $this->actingAs($user)->getJson($url)->assertJsonPath('checks.equipment.0.text', 'قميص رسمي ×2');
        $this->actingAs($user)->postJson("$url/sign", ['department' => 'equipment'])->assertStatus(422);
        $this->actingAs($user)->postJson("$url/sign", ['department' => 'equipment', 'note' => 'يرجعه غداً'])
            ->assertOk()->assertJsonPath('data.equipment_status', 'مكتمل')->assertJsonPath('signers.equipment', 'مسؤول العتاد');

        // A signature can be withdrawn
        $this->actingAs($user)->postJson("$url/unsign", ['department' => 'equipment'])->assertOk()->assertJsonPath('data.equipment_status', null);
        $this->actingAs($user)->postJson("$url/sign", ['department' => 'equipment', 'note' => 'يرجعه غداً'])->assertOk();

        // Step 3: closing waits for every department
        $this->actingAs($user)->postJson("$url/close")->assertStatus(422);
        foreach (['admin', 'sporting', 'medical', 'financial'] as $department) {
            $this->actingAs($user)->postJson("$url/sign", ['department' => $department])->assertOk();
        }
        $this->actingAs($user)->postJson("$url/close")->assertOk()->assertJsonPath('data.player_signature', true);
        $this->assertSame('inactive', $player->fresh()->status);

        // Closed: no more signatures; deleting the card makes the member active again
        $this->actingAs($user)->postJson("$url/unsign", ['department' => 'admin'])->assertStatus(422);
        $this->actingAs($user)->deleteJson($url)->assertOk();
        $this->assertSame('active', $player->fresh()->status);
    }

    public function test_each_department_shows_what_is_pending(): void
    {
        $user = User::factory()->create();
        $player = $this->player();
        $this->actingAs($user)->postJson('/api/player-clearance', ['player_id' => $player->id, 'exit_date' => '2026-10-20', 'exit_reason' => 'فسخ عقد'])->assertOk();

        DB::table('payment_expenses')->insert([
            ['individuals_id' => $player->id, 'amount_Nature' => 'سلفة', 'amount' => 20000],
            ['individuals_id' => $player->id, 'amount_Nature' => 'إرجاع سلفة', 'amount' => 5000],
        ]);
        DB::table('player_medical_records')->insert(['player_id' => $player->id, 'injury_nature' => 'التواء', 'record_status' => 'مفتوح/مصاب']);
        DB::table('player_medical_records')->insert(['player_id' => $player->id, 'injury_nature' => 'كدمة', 'record_status' => 'مغلق/متعافي']);
        DB::table('disciplinary_cases')->insert(['individuals_id' => $player->id, 'description' => 'شجار', 'case_status' => 'مفتوح']);
        DB::table('contracts')->insert(['individuals_id' => $player->id, 'end_date' => '2027-06-30', 'status' => 'active', 'Contract_value' => 0]);

        $checks = $this->actingAs($user)->getJson("/api/player-clearance/{$player->id}")->assertOk()->json('checks');
        $this->assertSame([['text' => 'سلفة غير مسترجعة', 'amount' => 15000]], $checks['financial']);
        $this->assertSame([['text' => 'التواء — مفتوح/مصاب']], $checks['medical']);
        $this->assertSame('إجراء تأديبي مفتوح: شجار', $checks['sporting'][0]['text']);
        $this->assertSame('عقد ساري حتى 30/06/2027', $checks['admin'][0]['text']);
        $this->assertSame([], $checks['equipment']);
    }
}

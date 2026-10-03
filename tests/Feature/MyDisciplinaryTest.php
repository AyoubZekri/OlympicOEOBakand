<?php

namespace Tests\Feature;

use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\Individual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyDisciplinaryTest extends TestCase
{
    use RefreshDatabase;

    private function caseFor(Individual $member, string $date, string $type, string $notes = ''): DisciplinaryCase
    {
        $case = DisciplinaryCase::create([
            'individuals_id' => $member->id,
            'incident_date' => $date,
            'description' => 'سبب ' . $date,
            'case_status' => 'مفتوح',
        ]);
        DisciplinaryAction::create([
            'case_id' => $case->id,
            'action_type' => $type,
            'action_date' => $date,
            'admin_notes' => $notes,
            'decision_outcome' => 'خصم',
        ]);

        return $case;
    }

    public function test_a_user_sees_only_the_actions_of_their_member(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $someone = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم', 'user_id' => $other->id]);

        $this->caseFor($me, '2026-09-01', 'تنبيه', 'ملاحظة داخلية');
        $this->caseFor($me, '2026-09-20', 'إنذار');
        $this->caseFor($someone, '2026-09-10', 'طلب توضيح');

        $data = $this->actingAs($user)->getJson('/api/disciplinary/mine')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('إنذار', $data[0]['actionType']); // newest first
        $this->assertSame('تنبيه', $data[1]['actionType']);
        $this->assertSame('خصم', $data[1]['decision_outcome']);
        // The administration's notes are its answer to the member
        $this->assertSame('ملاحظة داخلية', $data[1]['admin_notes']);
        $this->assertArrayNotHasKey('memberName', $data[1]);
    }

    public function test_a_user_without_a_member_sees_nothing_and_login_is_required(): void
    {
        $this->getJson('/api/disciplinary/mine')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/disciplinary/mine')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_member_answers_a_clarification_request_until_the_decision(): void
    {
        $user = User::factory()->create();
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $other = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);

        $request = DisciplinaryCase::create(['individuals_id' => $me->id, 'incident_date' => '2026-09-01', 'description' => 'غياب', 'case_status' => 'مفتوح']);
        $action = DisciplinaryAction::create(['case_id' => $request->id, 'action_type' => 'طلب توضيح', 'action_date' => '2026-09-01']);
        $warning = $this->caseFor($me, '2026-09-02', 'إنذار');
        $notMine = DisciplinaryCase::create(['individuals_id' => $other->id, 'incident_date' => '2026-09-03', 'description' => 'x', 'case_status' => 'مفتوح']);
        DisciplinaryAction::create(['case_id' => $notMine->id, 'action_type' => 'طلب توضيح', 'action_date' => '2026-09-03']);

        $this->actingAs($user)->postJson('/api/disciplinary/mine/reply', ['id' => $request->id, 'player_statements' => 'كنت مريضاً'])->assertOk();
        $this->assertSame('كنت مريضاً', $action->fresh()->player_statements);

        // Not mine, not a clarification request, empty
        $this->postJson('/api/disciplinary/mine/reply', ['id' => $notMine->id, 'player_statements' => 'x'])->assertForbidden();
        $this->postJson('/api/disciplinary/mine/reply', ['id' => $warning->id, 'player_statements' => 'x'])->assertStatus(422);
        $this->postJson('/api/disciplinary/mine/reply', ['id' => $request->id, 'player_statements' => ''])->assertStatus(422);

        // After the decision the reply is closed
        $action->update(['admin_notes' => 'خصم من المنحة']);
        $this->postJson('/api/disciplinary/mine/reply', ['id' => $request->id, 'player_statements' => 'تعديل'])->assertStatus(422);
        $this->assertSame('كنت مريضاً', $action->fresh()->player_statements);
    }
}

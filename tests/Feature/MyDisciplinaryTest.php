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
        // The administration's internal notes stay internal
        $this->assertArrayNotHasKey('admin_notes', $data[1]);
        $this->assertArrayNotHasKey('memberName', $data[1]);
    }

    public function test_a_user_without_a_member_sees_nothing_and_login_is_required(): void
    {
        $this->getJson('/api/disciplinary/mine')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/disciplinary/mine')->assertOk()->assertJsonCount(0, 'data');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\Individual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expense_and_an_exceptional_one_take_a_member_or_none(): void
    {
        $user = User::factory()->create();
        $member = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $cash = Fund::create(['name' => 'الصندوق', 'type' => 'wallet', 'current_balance' => 100000, 'status' => 'active']);
        $expense = ['transactionType' => 'مصروف', 'amount' => 5000, 'paymentMethod' => 'نقدا', 'paymentDate' => '2026-10-25', 'amountNature' => 'تجهيزات', 'fund_id' => $cash->id];
        $exceptional = ['transactionType' => 'مصاريف استثنائية', 'amount' => 3000, 'paymentMethod' => 'نقدا', 'paymentDate' => '2026-10-25', 'amountNature' => 'اخرى'];

        // With a member: kept
        $this->actingAs($user)->postJson('/api/payments/create', $expense + ['memberId' => $member->id])->assertCreated()->assertJsonPath('memberId', (string) $member->id);
        $this->actingAs($user)->postJson('/api/payments/create', $exceptional + ['memberId' => $member->id])->assertCreated()->assertJsonPath('memberId', (string) $member->id);

        // Without one: still saved
        $this->actingAs($user)->postJson('/api/payments/create', $expense)->assertCreated()->assertJsonPath('memberId', '');
        $this->actingAs($user)->postJson('/api/payments/create', $exceptional)->assertCreated();

        // A member that does not exist: refused
        $this->assertTrue($this->actingAs($user)->postJson('/api/payments/create', $expense + ['memberId' => 99999])->isClientError());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Individual;
use App\Models\PaymentExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTransferableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_payment_says_whether_its_amount_can_be_transferred(): void
    {
        $user = User::factory()->create();
        $member = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $payment = ['memberId' => $member->id, 'amount' => 30000, 'paymentMethod' => 'نقدا', 'paymentDate' => '2026-10-11', 'amountNature' => 'منحة مقابلات'];

        // Not said: no (the column's default)
        $id = $this->actingAs($user)->postJson('/api/payments/create', $payment)->assertCreated()->assertJsonPath('isTransferable', false)->json('id');

        // Yes, sent as the form data sends it ("true" / "1")
        $this->actingAs($user)->postJson('/api/payments/create', $payment + ['is_transferable' => 'true'])->assertJsonPath('isTransferable', true);
        $this->actingAs($user)->postJson('/api/payments/update', ['id' => $id, 'is_transferable' => '1'])->assertOk();
        $this->assertTrue(PaymentExpense::find($id)->is_transferable);

        $listed = collect($this->actingAs($user)->getJson('/api/payments')->json())->firstWhere('id', $id);
        $this->assertTrue($listed['isTransferable']);
    }

    public function test_a_transferable_payment_is_paid_from_another_fund(): void
    {
        $user = User::factory()->create();
        $member = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $cash = Fund::create(['name' => 'الصندوق', 'type' => 'wallet', 'current_balance' => 100000, 'status' => 'active']);
        $bank = Fund::create(['name' => 'البنك', 'type' => 'bank', 'current_balance' => 500000, 'status' => 'active']);
        $payment = ['memberId' => $member->id, 'amount' => 30000, 'paymentMethod' => 'نقدا', 'paymentDate' => '2026-10-11', 'amountNature' => 'منحة مقابلات', 'fund_id' => $cash->id];

        // Not transferable: refused
        $no = $this->actingAs($user)->postJson('/api/payments/create', $payment)->json('id');
        $this->postJson('/api/payments/transfer', ['id' => $no, 'fund_id' => $bank->id])->assertStatus(422);
        $this->assertSame(70000.0, (float) $cash->fresh()->current_balance);

        // Transferable: back into the cash fund, out of the bank
        $id = $this->postJson('/api/payments/create', $payment + ['is_transferable' => true])->json('id');
        $this->assertSame(40000.0, (float) $cash->fresh()->current_balance);
        $this->postJson('/api/payments/transfer', ['id' => $id, 'fund_id' => $cash->id])->assertStatus(422); // its own fund
        $this->postJson('/api/payments/transfer', ['id' => $id, 'fund_id' => $bank->id])->assertOk()->assertJsonPath('fund_id', (string) $bank->id);

        $this->assertSame(70000.0, (float) $cash->fresh()->current_balance);
        $this->assertSame(470000.0, (float) $bank->fresh()->current_balance);
        $this->assertSame(1, FundTransaction::where('fund_id', $cash->id)->where('type', 'إرجاع')->count());
        $this->assertSame(1, FundTransaction::where('fund_id', $bank->id)->where('type', 'سحب')->where('description', 'like', '%محوّل من الصندوق%')->count());
    }
}

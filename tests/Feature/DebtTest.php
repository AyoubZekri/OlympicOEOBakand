<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\PaymentExpense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Fund $cash;
    private Fund $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'admin', 'type' => 'full', 'permissions' => '{}'])->id]);
        $this->cash = Fund::create(['name' => 'الصندوق', 'type' => 'wallet', 'current_balance' => 100000, 'status' => 'active']);
        $this->bank = Fund::create(['name' => 'البنك', 'type' => 'bank', 'current_balance' => 500000, 'status' => 'active']);
    }

    private function balance(Fund $f): float
    {
        return (float) $f->fresh()->current_balance;
    }

    public function test_a_loan_goes_into_its_fund_and_can_be_paid_back_in_parts(): void
    {
        $debt = $this->actingAs($this->admin)->postJson('/api/debts/create', [
            'kind' => 'loan',
            'creditor' => 'أحمد',
            'amount' => 200000,
            'debt_date' => '2026-10-01',
            'fund_id' => $this->cash->id,
        ])->assertCreated()->json('data');

        $this->assertSame(300000.0, $this->balance($this->cash));
        $this->assertDatabaseHas('fund_transactions', ['fund_id' => $this->cash->id, 'type' => 'استلاف', 'amount' => 200000]);
        $this->assertSame('open', $debt['status']);

        // Half of it, from another fund
        $after = $this->postJson('/api/debts/repay', ['debt_id' => $debt['id'], 'amount' => 100000, 'paid_on' => '2026-10-05', 'fund_id' => $this->bank->id])
            ->assertCreated()->json('data');
        $this->assertSame(400000.0, $this->balance($this->bank));
        $this->assertSame('partial', $after['status']);
        $this->assertEquals(100000, $after['remaining']);
        // Not a fund operation: the balance moves, and the paid value is in the debt's own column
        $this->assertDatabaseMissing('fund_transactions', ['type' => 'تسديد دين']);
        $this->assertSame(1, FundTransaction::count());
        $this->assertEquals(100000, Debt::find($debt['id'])->paid_amount);
        $this->assertEquals(100000, $after['paid_amount']);
        // A loan repayment is not an expense
        $this->assertSame(0, PaymentExpense::count());

        // More than what is left is refused
        $this->postJson('/api/debts/repay', ['debt_id' => $debt['id'], 'amount' => 150000, 'paid_on' => '2026-10-06', 'fund_id' => $this->cash->id])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $done = $this->postJson('/api/debts/repay', ['debt_id' => $debt['id'], 'amount' => 100000, 'paid_on' => '2026-10-06', 'fund_id' => $this->cash->id])->json('data');
        $this->assertSame('paid', $done['status']);
        $this->assertSame(200000.0, $this->balance($this->cash));

        // Cancelling a repayment puts the money back
        $this->postJson('/api/debts/repayments/delete', ['id' => $done['repayments'][0]['id']])->assertOk();
        $this->assertSame(300000.0, $this->balance($this->cash));
        $this->assertSame(100000.0, Debt::find($debt['id'])->amount - Debt::find($debt['id'])->repaid());
    }

    public function test_editing_a_loan_moves_its_money_and_deleting_it_cancels_everything(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/debts/create', [
            'kind' => 'loan', 'creditor' => 'أحمد', 'amount' => 50000, 'debt_date' => '2026-10-01', 'fund_id' => $this->cash->id,
        ])->json('data.id');

        $this->postJson('/api/debts/update', [
            'id' => $id, 'creditor' => 'أحمد', 'amount' => 80000, 'debt_date' => '2026-10-01', 'fund_id' => $this->bank->id,
        ])->assertOk();
        $this->assertSame(100000.0, $this->balance($this->cash));
        $this->assertSame(580000.0, $this->balance($this->bank));
        $this->assertSame(1, FundTransaction::where('type', 'استلاف')->count());

        $this->postJson('/api/debts/repay', ['debt_id' => $id, 'amount' => 30000, 'paid_on' => '2026-10-02', 'fund_id' => $this->bank->id]);
        // Cannot go below what was already paid
        $this->postJson('/api/debts/update', ['id' => $id, 'creditor' => 'أحمد', 'amount' => 20000, 'debt_date' => '2026-10-01', 'fund_id' => $this->bank->id])
            ->assertStatus(422);

        $this->postJson('/api/debts/delete', ['id' => $id])->assertOk();
        $this->assertSame(500000.0, $this->balance($this->bank));
        $this->assertSame(0, FundTransaction::count());
        $this->assertSame(0, Debt::count());
    }

    public function test_a_purchase_is_an_expense_only_when_paid(): void
    {
        $debt = $this->actingAs($this->admin)->postJson('/api/debts/create', [
            'kind' => 'purchase',
            'creditor' => 'محل الرياضة',
            'title' => 'ملابس الفريق',
            'expense_nature' => 'تجهيزات',
            'amount' => 90000,
            'debt_date' => '2026-10-01',
            'due_date' => '2026-11-01',
        ])->assertCreated()->json('data');

        // Nothing paid, nothing moved
        $this->assertSame(0, PaymentExpense::count());
        $this->assertSame(100000.0, $this->balance($this->cash));

        $after = $this->postJson('/api/debts/repay', ['debt_id' => $debt['id'], 'amount' => 45000, 'paid_on' => '2026-10-10', 'fund_id' => $this->cash->id, 'payment_method' => 'نقدا'])
            ->json('data');
        $this->assertEquals(45000, $after['remaining']);
        $this->assertSame(55000.0, $this->balance($this->cash));
        $payment = PaymentExpense::first();
        $this->assertSame('تجهيزات', $payment->amount_Nature);
        $this->assertSame('مصروف', $payment->transaction_type);
        $this->assertSame((int) $this->cash->id, (int) $payment->fund_id);
        $this->assertStringContainsString('محل الرياضة', $payment->Occasion_Reason_numper);
        $this->assertDatabaseHas('fund_transactions', ['type' => 'سحب', 'amount' => 45000, 'payment_expenses_id' => $payment->id]);

        // Paid outside the funds: still an expense, no fund moves
        $this->postJson('/api/debts/repay', ['debt_id' => $debt['id'], 'amount' => 5000, 'paid_on' => '2026-10-11'])->assertCreated();
        $this->assertSame(2, PaymentExpense::count());
        $this->assertSame(55000.0, $this->balance($this->cash));

        // Deleting the expense from the payments page cancels that repayment
        $payment->delete();
        $this->assertEquals(85000, $this->getJson('/api/debts')->json('data.0.remaining'));

        // Deleting the debt removes its remaining expense
        $this->postJson('/api/debts/delete', ['id' => $debt['id']])->assertOk();
        $this->assertSame(0, PaymentExpense::count());
    }

    public function test_validation(): void
    {
        $this->actingAs($this->admin)->postJson('/api/debts/create', ['kind' => 'loan', 'creditor' => 'x', 'amount' => 10, 'debt_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('fund_id');
        $this->postJson('/api/debts/create', ['kind' => 'purchase', 'creditor' => '', 'amount' => 0, 'debt_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['creditor', 'amount']);
    }
}

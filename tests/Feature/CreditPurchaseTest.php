<?php

namespace Tests\Feature;

use App\Models\CreditPayment;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\PaymentExpense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Fund $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'admin', 'type' => 'full', 'permissions' => '{}'])->id]);
        $this->cash = Fund::create(['name' => 'الصندوق', 'type' => 'wallet', 'current_balance' => 100000, 'status' => 'active']);
    }

    private function balance(): float
    {
        return (float) $this->cash->fresh()->current_balance;
    }

    public function test_paying_a_purchase_on_credit_stays_on_its_row(): void
    {
        $credit = $this->actingAs($this->admin)->postJson('/api/payments/credit/create', [
            'creditor' => 'محل الرياضة',
            'title' => 'ملابس الفريق',
            'expense_nature' => 'تجهيزات',
            'amount' => 90000,
            'debt_date' => '2026-10-01',
            'due_date' => '2026-11-01',
        ])->assertCreated()->json('data');

        // One row of the payments & expenses table; not paid, so not an expense yet and no fund moves
        $this->assertSame(1, PaymentExpense::count());
        $this->assertTrue(PaymentExpense::find($credit['id'])->is_credit);
        $this->assertSame(100000.0, $this->balance());
        $this->assertCount(0, $this->getJson('/api/payments')->json());

        // Half, from the fund: still one row, its paid column grows
        $after = $this->postJson('/api/payments/credit/pay', ['debt_id' => $credit['id'], 'amount' => 45000, 'paid_on' => '2026-10-10', 'fund_id' => $this->cash->id, 'payment_method' => 'نقدا'])
            ->assertCreated()->json('data');
        $this->assertSame(1, PaymentExpense::count());
        $this->assertEquals(45000, PaymentExpense::find($credit['id'])->paid_amount);
        $this->assertEquals(45000, $after['paid_amount']);
        $this->assertEquals(45000, $after['remaining']);
        $this->assertSame('partial', $after['status']);
        $this->assertSame(55000.0, $this->balance());
        $this->assertDatabaseHas('fund_transactions', ['type' => 'سحب', 'amount' => 45000, 'payment_expenses_id' => $credit['id']]);

        // In the payments list it counts for what has been paid
        $payments = $this->getJson('/api/payments')->json();
        $this->assertCount(1, $payments);
        $this->assertEquals(45000, $payments[0]['amount']);
        $this->assertEquals(90000, $payments[0]['creditTotal']);
        $this->assertTrue($payments[0]['isCredit']);
        $this->assertSame((string) $this->cash->id, $payments[0]['fund_id']);

        // More than what is left is refused
        $this->postJson('/api/payments/credit/pay', ['debt_id' => $credit['id'], 'amount' => 50000, 'paid_on' => '2026-10-11'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        // The rest, outside the funds
        $done = $this->postJson('/api/payments/credit/pay', ['debt_id' => $credit['id'], 'amount' => 45000, 'paid_on' => '2026-10-12'])->json('data');
        $this->assertSame('paid', $done['status']);
        $this->assertSame(1, PaymentExpense::count());
        $this->assertCount(2, $done['repayments']);
        $this->assertSame(55000.0, $this->balance());

        // Cancelling the fund payment puts the money back
        $fundPayment = collect($done['repayments'])->firstWhere('fund_id', $this->cash->id);
        $back = $this->postJson('/api/payments/credit/payments/delete', ['id' => $fundPayment['id']])->assertOk()->json('data');
        $this->assertEquals(45000, $back['remaining']);
        $this->assertEquals(45000, $back['paid_amount']);
        $this->assertSame(100000.0, $this->balance());
        $this->assertSame(0, FundTransaction::count());
    }

    public function test_edit_validation_and_delete(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/payments/credit/create', [
            'creditor' => 'مطعم', 'amount' => 30000, 'debt_date' => '2026-10-01',
        ])->json('data.id');
        $this->assertSame('اخرى', PaymentExpense::find($id)->amount_Nature);

        $this->postJson('/api/payments/credit/pay', ['debt_id' => $id, 'amount' => 20000, 'paid_on' => '2026-10-02', 'fund_id' => $this->cash->id]);
        $this->postJson('/api/payments/credit/update', ['id' => $id, 'creditor' => 'مطعم', 'amount' => 10000, 'debt_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson('/api/payments/credit/update', ['id' => $id, 'creditor' => 'مطعم الوفاء', 'amount' => 35000, 'debt_date' => '2026-10-01', 'expense_nature' => 'إطعام'])
            ->assertOk()->assertJsonPath('data.remaining', 15000);

        $this->postJson('/api/payments/credit/create', ['creditor' => '', 'amount' => 0, 'debt_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['creditor', 'amount']);

        // Deleting the purchase cancels its payments: the money goes back to the fund
        $this->assertSame(80000.0, $this->balance());
        $this->postJson('/api/payments/credit/delete', ['id' => $id])->assertOk();
        $this->assertNull(PaymentExpense::find($id));
        $this->assertSame(0, CreditPayment::count());
        $this->assertSame(100000.0, $this->balance());
    }
}

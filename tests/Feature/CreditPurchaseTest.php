<?php

namespace Tests\Feature;

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

    public function test_a_purchase_on_credit_is_in_the_payments_table_and_paid_in_parts(): void
    {
        $credit = $this->actingAs($this->admin)->postJson('/api/payments/credit/create', [
            'creditor' => 'محل الرياضة',
            'title' => 'ملابس الفريق',
            'expense_nature' => 'تجهيزات',
            'amount' => 90000,
            'debt_date' => '2026-10-01',
            'due_date' => '2026-11-01',
        ])->assertCreated()->json('data');

        // A row of the payments & expenses table, but not a payment: no fund moves, not in the payments list
        $row = PaymentExpense::find($credit['id']);
        $this->assertTrue($row->is_credit);
        $this->assertSame('تجهيزات', $row->amount_Nature);
        $this->assertSame('محل الرياضة', $row->creditor);
        $this->assertSame(100000.0, $this->balance());
        $this->assertCount(0, $this->getJson('/api/payments')->json());
        $this->assertSame('open', $credit['status']);
        $this->assertSame('purchase', $credit['kind']);

        // Half, from the fund
        $after = $this->postJson('/api/payments/credit/pay', ['debt_id' => $credit['id'], 'amount' => 45000, 'paid_on' => '2026-10-10', 'fund_id' => $this->cash->id, 'payment_method' => 'نقدا'])
            ->assertCreated()->json('data');
        $this->assertEquals(45000, $after['remaining']);
        $this->assertSame('partial', $after['status']);
        $this->assertSame(55000.0, $this->balance());
        $payments = $this->getJson('/api/payments')->json();
        $this->assertCount(1, $payments);
        $this->assertEquals(45000, $payments[0]['amount']);
        $this->assertSame('تجهيزات', $payments[0]['amountNature']);
        $this->assertSame((string) $credit['id'], $payments[0]['creditId']);
        $this->assertDatabaseHas('fund_transactions', ['type' => 'سحب', 'amount' => 45000, 'payment_expenses_id' => $payments[0]['id']]);

        // More than what is left is refused
        $this->postJson('/api/payments/credit/pay', ['debt_id' => $credit['id'], 'amount' => 50000, 'paid_on' => '2026-10-11'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        // The rest, outside the funds
        $done = $this->postJson('/api/payments/credit/pay', ['debt_id' => $credit['id'], 'amount' => 45000, 'paid_on' => '2026-10-12'])->json('data');
        $this->assertSame('paid', $done['status']);
        $this->assertSame(55000.0, $this->balance());
        $this->assertCount(2, $done['repayments']);

        // Cancelling the fund payment puts the money back and removes the expense
        $fundPayment = collect($done['repayments'])->firstWhere('fund_id', $this->cash->id);
        $back = $this->postJson('/api/payments/credit/payments/delete', ['id' => $fundPayment['id']])->assertOk()->json('data');
        $this->assertEquals(45000, $back['remaining']);
        $this->assertSame(100000.0, $this->balance());
        $this->assertSame(0, FundTransaction::count());

        // Deleting a payment from the payments page also lowers what is paid
        PaymentExpense::where('credit_id', $credit['id'])->first()->delete();
        $this->assertEquals(90000, $this->getJson('/api/payments/credit')->json('data.0.remaining'));
    }

    public function test_edit_validation_and_delete(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/payments/credit/create', [
            'creditor' => 'مطعم', 'amount' => 30000, 'debt_date' => '2026-10-01',
        ])->json('data.id');
        $this->assertSame('اخرى', PaymentExpense::find($id)->amount_Nature);

        $this->postJson('/api/payments/credit/pay', ['debt_id' => $id, 'amount' => 20000, 'paid_on' => '2026-10-02']);
        $this->postJson('/api/payments/credit/update', ['id' => $id, 'creditor' => 'مطعم', 'amount' => 10000, 'debt_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson('/api/payments/credit/update', ['id' => $id, 'creditor' => 'مطعم الوفاء', 'amount' => 35000, 'debt_date' => '2026-10-01', 'expense_nature' => 'إطعام'])
            ->assertOk()->assertJsonPath('data.remaining', 15000);

        $this->postJson('/api/payments/credit/create', ['creditor' => '', 'amount' => 0, 'debt_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['creditor', 'amount']);

        // Deleting the purchase keeps the payment already made as an expense
        $this->postJson('/api/payments/credit/delete', ['id' => $id])->assertOk();
        $this->assertNull(PaymentExpense::find($id));
        $this->assertCount(1, $this->getJson('/api/payments')->json());
    }
}

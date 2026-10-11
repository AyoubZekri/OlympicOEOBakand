<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundTransactionDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_operation_keeps_the_balances(): void
    {
        $user = User::factory()->create();
        $cash = Fund::create(['name' => 'الصندوق', 'type' => 'wallet', 'current_balance' => 100000, 'status' => 'active']);
        $bank = Fund::create(['name' => 'البنك', 'type' => 'bank', 'current_balance' => 500000, 'status' => 'active']);
        foreach ([
            ['fund_id' => $cash->id, 'type' => 'إيداع', 'amount' => 20000],
            ['fund_id' => $cash->id, 'type' => 'سحب', 'amount' => 5000],
            ['fund_id' => $cash->id, 'type' => 'تحويل', 'amount' => 10000, 'to_fund_id' => $bank->id],
        ] as $operation) {
            $tx = FundTransaction::create($operation);
            $this->actingAs($user)->postJson('/api/transactions/delete', ['id' => $tx->id])->assertOk();
        }

        $this->assertSame(0, FundTransaction::count());
        $this->assertSame(100000.0, (float) $cash->fresh()->current_balance);
        $this->assertSame(500000.0, (float) $bank->fresh()->current_balance);
    }
}

<?php

namespace Tests\Feature;

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
}

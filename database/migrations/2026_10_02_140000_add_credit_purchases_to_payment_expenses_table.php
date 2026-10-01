<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases on credit live in the payments & expenses table:
 * - the purchase itself: a row with is_credit = 1 (who we owe, the full amount, the due date); it is not money paid;
 * - every payment made on it: a normal expense row whose credit_id is the purchase.
 * Purchases already recorded as debts (kind "purchase") are moved here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->boolean('is_credit')->default(false)->index();
            $table->foreignId('credit_id')->nullable()->constrained('payment_expenses')->nullOnDelete();
            $table->string('creditor')->nullable();
            $table->string('creditor_phone')->nullable();
            $table->date('due_date')->nullable();
        });

        if (! Schema::hasTable('debts')) {
            return;
        }

        DB::table('debts')->where('kind', 'purchase')->orderBy('id')->get()->each(function ($debt) {
            $now = now();
            $creditId = DB::table('payment_expenses')->insertGetId([
                'individuals_id' => null,
                'amount' => $debt->amount,
                'payment_method' => 'بالدين',
                'Payments_data' => substr((string) $debt->debt_date, 0, 10),
                'amount_Nature' => $debt->expense_nature ?: 'اخرى',
                'transaction_type' => 'مصروف',
                'Occasion_Reason_numper' => $debt->title,
                'notes' => $debt->notes,
                'fund_id' => null,
                'Number_of_months' => 1,
                'is_credit' => true,
                'creditor' => $debt->creditor,
                'creditor_phone' => $debt->creditor_phone,
                'due_date' => $debt->due_date,
                'created_at' => $debt->created_at ?? $now,
                'updated_at' => $now,
            ]);

            // Its payments are already expenses: link them to the purchase
            $paymentIds = DB::table('debt_repayments')->where('debt_id', $debt->id)->whereNotNull('payment_expense_id')->pluck('payment_expense_id');
            DB::table('payment_expenses')->whereIn('id', $paymentIds)->update(['credit_id' => $creditId]);

            DB::table('debt_repayments')->where('debt_id', $debt->id)->delete();
            DB::table('debts')->where('id', $debt->id)->delete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_id');
            $table->dropColumn(['is_credit', 'creditor', 'creditor_phone', 'due_date']);
        });
    }
};

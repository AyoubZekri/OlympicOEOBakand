<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase on credit stays one row of the payments & expenses table:
 * paying it fills its paid_amount column (no new row). Each payment is kept in credit_payments
 * (date, fund, method) so it can be shown and cancelled.
 * Payments already recorded as separate expense rows (credit_id) are folded into their purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
        });

        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_expense_id')->constrained('payment_expenses')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('fund_transaction_id')->nullable()->constrained('fund_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('payment_expenses')->whereNotNull('credit_id')->orderBy('id')->get()->each(function ($row) {
            $tx = DB::table('fund_transactions')->where('payment_expenses_id', $row->id)->where('type', 'سحب')->first();
            DB::table('credit_payments')->insert([
                'payment_expense_id' => $row->credit_id,
                'amount' => $row->amount,
                'paid_on' => substr((string) $row->Payments_data, 0, 10) ?: now()->toDateString(),
                'fund_id' => $row->fund_id ?: null,
                'payment_method' => $row->payment_method,
                'notes' => $row->notes,
                'fund_transaction_id' => $tx?->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
            // The fund withdrawal now belongs to the purchase's row
            DB::table('fund_transactions')->where('payment_expenses_id', $row->id)->update(['payment_expenses_id' => $row->credit_id]);
            DB::table('payment_expenses')->where('id', $row->id)->delete();
        });

        DB::table('payment_expenses')->where('is_credit', true)->orderBy('id')->get()->each(function ($credit) {
            $paid = (float) DB::table('credit_payments')->where('payment_expense_id', $credit->id)->sum('amount');
            $last = DB::table('credit_payments')->where('payment_expense_id', $credit->id)->orderByDesc('paid_on')->orderByDesc('id')->first();
            DB::table('payment_expenses')->where('id', $credit->id)->update([
                'paid_amount' => round($paid, 2),
                'fund_id' => $last?->fund_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};

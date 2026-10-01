<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** What has been paid on each debt, in its own column (the sum of its repayments) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
        });

        // Debts already repaid: their repayments so far
        DB::table('debts')->orderBy('id')->each(function ($debt) {
            $paid = (float) DB::table('debt_repayments')->where('debt_id', $debt->id)->sum('amount');
            DB::table('debts')->where('id', $debt->id)->update(['paid_amount' => round($paid, 2)]);
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};

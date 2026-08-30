<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionTypeToPaymentExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_expenses', 'transaction_type')) {
                $table->string('transaction_type')->nullable()->after('id')->default('ÏÝÚ');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('payment_expenses', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });
    }
}

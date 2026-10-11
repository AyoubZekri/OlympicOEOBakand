<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A payment to a member: whether its amount can be transferred (asked when it is saved; no by default) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->boolean('is_transferable')->default(false)->after('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::table('payment_expenses', function (Blueprint $table) {
            $table->dropColumn('is_transferable');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
            $table->foreignId('payment_expenses_id')->nullable()->constrained('payment_expenses')->nullOnDelete();
            $table->string('type'); // 'income', 'expense', 'transfer'
            $table->decimal('amount', 15, 2);
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('transaction_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
    }
};

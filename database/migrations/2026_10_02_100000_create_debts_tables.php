<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The club's debts and their repayments.
 * kind "loan": money borrowed from someone and put into a fund; "purchase": something bought and not paid yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // loan | purchase
            $table->string('creditor');
            $table->string('creditor_phone')->nullable();
            $table->string('title')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('debt_date');
            $table->date('due_date')->nullable();
            // loan: the fund the borrowed money went into, and the transaction that put it there
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('fund_transaction_id')->nullable()->constrained('fund_transactions')->nullOnDelete();
            // purchase: the expense nature its repayments are recorded with
            $table->string('expense_nature')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('debt_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->string('payment_method')->nullable();
            // The records a repayment made: the expense (purchase) and the fund withdrawal.
            // Deleting one of them from its own page cancels the repayment.
            $table->foreignId('payment_expense_id')->nullable()->constrained('payment_expenses')->cascadeOnDelete();
            $table->foreignId('fund_transaction_id')->nullable()->constrained('fund_transactions')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_repayments');
        Schema::dropIfExists('debts');
    }
};

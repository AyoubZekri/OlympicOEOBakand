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
        Schema::create('payment_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individuals_id')->nullable()->constrained('individuals')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('Payments_data')->nullable(); // Schema says 'data', changed to string for date or JSON
            $table->string('payment_method')->nullable();
            $table->string('amount_Nature')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('Occasion_Reason_numper')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_expenses');
    }
};

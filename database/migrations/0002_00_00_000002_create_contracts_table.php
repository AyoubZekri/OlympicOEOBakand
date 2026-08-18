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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individuals_id')->constrained('individuals')->cascadeOnDelete();
            $table->integer('numper')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('Contract_value', 15, 2)->nullable();
            $table->integer('Number_payments')->nullable();
            $table->decimal('Monthly_Salary', 15, 2)->nullable();
            $table->decimal('Winning_Bonus', 15, 2)->nullable();
            $table->decimal('Goals_Bonus', 15, 2)->nullable();
            $table->string('nots')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

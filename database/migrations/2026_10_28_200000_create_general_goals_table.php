<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The management's general goals: the goal, the member responsible for it and the season ("2026 - 2027") */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_goals', function (Blueprint $table) {
            $table->id();
            $table->text('goal');
            // The member responsible for it; the goal stays if the member is deleted
            $table->foreignId('individual_id')->nullable()->constrained('individuals')->nullOnDelete();
            $table->string('season', 20)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_goals');
    }
};

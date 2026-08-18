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
        Schema::create('individuals', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'player', 'coach', 'employee'
            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('birth_date')->nullable();
            $table->integer('Shirt_number')->nullable();
            $table->string('status')->default('active'); // 'active', 'inactive', 'suspended'
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete(); // null if employee not assigned to a team
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individuals');
    }
};

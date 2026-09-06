<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('player_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->date('exit_date')->nullable();
            $table->string('exit_reason')->nullable();
            $table->string('equipment_status')->nullable();
            $table->text('equipment_notes')->nullable();
            $table->foreignId('equipment_manager_id')->nullable()->constrained('individuals');
            $table->timestamp('equipment_cleared_at')->nullable();
            $table->string('admin_status')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->timestamp('admin_cleared_at')->nullable();
            $table->string('sporting_status')->nullable();
            $table->foreignId('sporting_director_id')->nullable()->constrained('individuals');
            $table->timestamp('sporting_cleared_at')->nullable();
            $table->string('financial_status')->nullable();
            $table->foreignId('finance_manager_id')->nullable()->constrained('individuals');
            $table->timestamp('finance_cleared_at')->nullable();
            $table->string('medical_status')->nullable();
            $table->foreignId('medical_staff_id')->nullable()->constrained('individuals');
            $table->timestamp('medical_cleared_at')->nullable();
            $table->text('general_notes')->nullable();
            $table->boolean('player_signature')->nullable();
            $table->timestamp('player_signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_clearances');
    }
};

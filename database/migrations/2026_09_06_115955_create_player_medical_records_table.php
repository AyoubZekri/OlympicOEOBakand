<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('player_medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->foreignId('doctor_id')->nullable()->constrained('individuals');
            $table->date('injury_date')->nullable();
            $table->string('incident_location')->nullable();
            $table->string('injury_nature')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('initial_recommendation')->nullable();
            $table->date('last_exam_date')->nullable();
            $table->string('medical_decision')->nullable();
            $table->text('restrictions')->nullable();
            $table->date('next_exam_date')->nullable();
            $table->string('record_status')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_medical_records');
    }
};

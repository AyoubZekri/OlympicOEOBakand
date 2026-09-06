<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('app_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->string('absence_type')->nullable();
            $table->string('event_category')->nullable();
            $table->date('event_date')->nullable();
            $table->foreignId('training_session_id')->nullable()->constrained('training_sessions');
            $table->string('record_source')->nullable();
            $table->string('duration')->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->boolean('is_justified')->nullable();
            $table->string('justification_status')->nullable();
            $table->foreignId('decision_by')->nullable()->constrained('individuals');
            $table->timestamp('decision_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_absences');
    }
};

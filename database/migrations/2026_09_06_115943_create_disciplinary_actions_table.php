<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('disciplinary_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('disciplinary_cases');
            $table->string('action_type')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamp('action_date')->nullable();
            $table->timestamp('deadline_or_hearing_date')->nullable();
            $table->string('hearing_location')->nullable();
            $table->text('player_statements')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('decision_outcome')->nullable();
            $table->text('decision_reasons')->nullable();
            $table->timestamp('effective_date')->nullable();
            $table->boolean('is_acknowledged')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('disciplinary_actions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('improvement_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->nullable()->constrained('player_evaluations');
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->date('program_start')->nullable();
            $table->date('program_end')->nullable();
            $table->text('areas_to_improve')->nullable();
            $table->text('specific_goals')->nullable();
            $table->text('actions_required')->nullable();
            $table->date('next_evaluation_date')->nullable();
            $table->boolean('is_acknowledged')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('improvement_programs');
    }
};

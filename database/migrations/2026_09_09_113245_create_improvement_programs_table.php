<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImprovementProgramsTable extends Migration
{
    public function up()
    {
        Schema::create('improvement_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('player_id');
            $table->date('program_start')->nullable();
            $table->date('program_end')->nullable();
            $table->text('areas_to_improve')->nullable();
            $table->text('specific_goals')->nullable();
            $table->text('actions_required')->nullable();
            $table->date('next_evaluation_date')->nullable();
            $table->boolean('is_acknowledged')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('improvement_programs');
    }
}

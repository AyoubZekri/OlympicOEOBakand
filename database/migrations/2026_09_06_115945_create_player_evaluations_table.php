<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('player_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->foreignId('coach_id')->nullable()->constrained('individuals');
            $table->foreignId('sporting_director_id')->nullable()->constrained('individuals');
            $table->string('season')->nullable();
            $table->string('evaluation_type')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->integer('matches_played')->nullable();
            $table->integer('minutes_played')->nullable();
            $table->integer('score_discipline')->nullable();
            $table->integer('score_fitness')->nullable();
            $table->integer('score_technical')->nullable();
            $table->integer('score_tactical')->nullable();
            $table->integer('score_match_performance')->nullable();
            $table->integer('score_instructions')->nullable();
            $table->integer('score_behavior')->nullable();
            $table->integer('total_score')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->string('recommendation')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_evaluations');
    }
};

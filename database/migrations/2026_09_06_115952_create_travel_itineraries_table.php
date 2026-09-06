<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('travel_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->nullable()->constrained('matches');
            $table->string('destination')->nullable();
            $table->string('travel_reason')->nullable();
            $table->string('departure_location')->nullable();
            $table->timestamp('departure_time')->nullable();
            $table->string('transport_method')->nullable();
            $table->string('accommodation_place')->nullable();
            $table->timestamp('return_time')->nullable();
            $table->foreignId('head_of_delegation_id')->nullable()->constrained('individuals');
            $table->text('staff_details')->nullable();
            $table->integer('players_count')->nullable();
            $table->time('schedule_departure')->nullable();
            $table->time('schedule_arrival')->nullable();
            $table->time('schedule_meal')->nullable();
            $table->time('schedule_tech_meeting')->nullable();
            $table->time('schedule_match')->nullable();
            $table->time('schedule_return')->nullable();
            $table->text('special_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('travel_itineraries');
    }
};

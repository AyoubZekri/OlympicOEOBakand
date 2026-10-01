<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A trip's category (team), and the members chosen for it: the accompanying staff and the players */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_itineraries', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('match_id')->constrained('teams')->nullOnDelete();
            $table->json('staff_ids')->nullable()->after('staff_details');
            $table->json('player_ids')->nullable()->after('players_count');
        });
    }

    public function down(): void
    {
        Schema::table('travel_itineraries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['staff_ids', 'player_ids']);
        });
    }
};

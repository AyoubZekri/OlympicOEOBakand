<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a match (scheduled, changed, postponed, cancelled, deleted), for the alerts of the category's members.
 * The match is kept as a copy: a deleted match is still announced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id'); // no foreign key: the notice outlives a deleted match
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('kind'); // created | updated | postponed | cancelled | restored | deleted
            $table->string('match_date')->nullable(); // "Y-m-d H:i", as the match stores it (Algeria time)
            $table->string('location')->nullable();
            $table->string('opponent')->nullable();
            $table->string('competition')->nullable();
            $table->json('previous')->nullable(); // updated: the date / place before the change
            $table->timestamps();
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_notices');
    }
};

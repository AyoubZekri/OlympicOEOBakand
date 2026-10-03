<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a training session (created, edited, cancelled, deleted), for the alerts of the category's members.
 * The session is kept as a copy: a deleted session is still announced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_session_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_session_id'); // no foreign key: the notice outlives a deleted session
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('kind'); // created | updated | cancelled | restored | deleted
            $table->date('session_date')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('location')->nullable();
            $table->json('previous')->nullable(); // updated: the date / times / place before the change
            $table->timestamps();
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_notices');
    }
};

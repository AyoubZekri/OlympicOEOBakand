<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a meeting (date / time / place / topic changed, deleted, a member taken off the invited list),
 * for the alerts of the members concerned. The meeting is kept as a copy: a deleted meeting is still announced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id'); // no foreign key: the notice outlives a deleted meeting
            $table->string('kind'); // updated | deleted | uninvited
            $table->json('member_ids'); // who is told
            $table->string('topic')->nullable();
            $table->string('date')->nullable();
            $table->string('time')->nullable();
            $table->string('location')->nullable();
            $table->json('previous')->nullable(); // updated: the values before the change
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_notices');
    }
};

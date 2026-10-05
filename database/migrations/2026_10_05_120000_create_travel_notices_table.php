<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a trip (departure / return / destination / meeting point / transport changed, deleted,
 * a member taken off it), for the alerts of the members concerned. The trip is kept as a copy: a deleted trip is still announced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('travel_id'); // no foreign key: the notice outlives a deleted trip
            $table->string('kind'); // updated | deleted | removed
            $table->json('member_ids'); // who is told
            $table->string('destination')->nullable();
            $table->string('departure_time')->nullable(); // "Y-m-d H:i"
            $table->string('return_time')->nullable();
            $table->string('departure_location')->nullable();
            $table->string('transport_method')->nullable();
            $table->json('previous')->nullable(); // updated: the values before the change
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_notices');
    }
};

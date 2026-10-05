<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a medical file (a new stage, a date or a detail changed, the file deleted), for the alerts of its member.
 * The file is kept as a copy: a deleted file is still announced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('record_id'); // no foreign key: the notice outlives a deleted file
            $table->unsignedBigInteger('player_id'); // the member told
            $table->string('kind'); // stage | updated | deleted
            $table->string('injury_nature')->nullable();
            $table->json('changes')->nullable(); // stage / updated: [{field, label, from, to}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_notices');
    }
};

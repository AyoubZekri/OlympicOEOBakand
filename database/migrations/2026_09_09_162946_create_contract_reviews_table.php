<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('club_representative_id')->nullable();
            $table->datetime('meeting_date')->nullable();
            $table->text('discussed_topics')->nullable();
            $table->text('club_proposal')->nullable();
            $table->text('player_position')->nullable();
            $table->text('outcome')->nullable();
            $table->boolean('requires_official_avenant')->default(0);
            $table->boolean('player_signature')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_reviews');
    }
};

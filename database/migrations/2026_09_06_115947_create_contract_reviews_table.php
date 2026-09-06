<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contract_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations');
            $table->foreignId('club_representative_id')->nullable()->constrained('individuals');
            $table->timestamp('meeting_date')->nullable();
            $table->text('discussed_topics')->nullable();
            $table->text('club_proposal')->nullable();
            $table->text('player_position')->nullable();
            $table->string('outcome')->nullable();
            $table->boolean('requires_official_avenant')->nullable();
            $table->boolean('player_signature')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_reviews');
    }
};

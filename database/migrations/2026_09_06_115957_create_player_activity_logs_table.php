<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('player_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('individuals');
            $table->timestamp('event_date')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_ref')->nullable();
            $table->string('subject')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('result_outcome')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_activity_logs');
    }
};

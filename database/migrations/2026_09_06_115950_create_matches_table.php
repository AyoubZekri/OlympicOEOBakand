<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('competition')->nullable();
            $table->string('opponent')->nullable();
            $table->string('match_title')->nullable();
            $table->timestamp('match_date')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('gathering_time')->nullable();
            $table->string('gathering_location')->nullable();
            $table->foreignId('coach_id')->nullable()->constrained('individuals');
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('matches');
    }
};

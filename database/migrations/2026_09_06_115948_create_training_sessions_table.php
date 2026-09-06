<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date')->nullable();
            $table->string('shift')->nullable();
            $table->string('location')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('individuals');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('training_sessions');
    }
};

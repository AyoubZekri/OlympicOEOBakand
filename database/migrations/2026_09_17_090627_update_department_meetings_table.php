<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('department_meetings', function (Blueprint $table) {
            $table->string('topic')->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('location')->nullable();
            $table->json('attendees')->nullable();
            $table->json('points')->nullable();
        });
    }

    public function down()
    {
        Schema::table('department_meetings', function (Blueprint $table) {
            $table->dropColumn(['topic', 'date', 'time', 'location', 'attendees', 'points']);
        });
    }
};

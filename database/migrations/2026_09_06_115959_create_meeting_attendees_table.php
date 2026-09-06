<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained('department_meetings');
            $table->foreignId('member_id')->nullable()->constrained('individuals');
        });
    }

    public function down()
    {
        Schema::dropIfExists('meeting_attendees');
    }
};

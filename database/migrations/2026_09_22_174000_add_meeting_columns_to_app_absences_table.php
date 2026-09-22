<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->unsignedBigInteger('meeting_id')->nullable()->after('training_session_id');
            $table->foreign('meeting_id')->references('id')->on('department_meetings')->onDelete('cascade');
            $table->string('meeting_topic')->nullable()->after('meeting_id');
        });
    }

    public function down()
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->dropForeign(['meeting_id']);
            $table->dropColumn(['meeting_id', 'meeting_topic']);
        });
    }
};

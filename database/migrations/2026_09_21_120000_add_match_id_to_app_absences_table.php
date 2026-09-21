<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->foreignId('match_id')->nullable()->after('training_session_id')->constrained('matches')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->dropForeign(['match_id']);
            $table->dropColumn('match_id');
        });
    }
};

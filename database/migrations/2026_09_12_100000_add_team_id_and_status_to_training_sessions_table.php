<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->string('status')->default('مجدولة'); // مجدولة، جارية، مكتملة، ملغاة
        });
    }

    public function down()
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
            $table->dropColumn('status');
        });
    }
};

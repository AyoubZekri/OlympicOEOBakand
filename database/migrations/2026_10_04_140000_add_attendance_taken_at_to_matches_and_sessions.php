<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the attendance sheet was saved. "Present" is stored as no absence row, so without this
 * a sheet where everyone came cannot be told apart from a sheet never taken (the managers' alerts need it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('attendance_taken_at')->nullable();
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->timestamp('attendance_taken_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('attendance_taken_at');
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('attendance_taken_at');
        });
    }
};

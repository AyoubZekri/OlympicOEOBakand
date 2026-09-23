<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('player_medical_records', function (Blueprint $table) {
    
        Schema::table('player_medical_records', function (Blueprint $table) {
            $table->date('absence_from')->nullable()->after('medical_decision');
            $table->date('absence_to')->nullable()->after('absence_from');
        });

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_medical_records', function (Blueprint $table) {
    
        Schema::table('player_medical_records', function (Blueprint $table) {
            $table->dropColumn(['absence_from', 'absence_to']);
        });

        });
    }
};

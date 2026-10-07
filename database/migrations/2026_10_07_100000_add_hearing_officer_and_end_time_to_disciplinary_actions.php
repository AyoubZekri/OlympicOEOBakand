<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A hearing (جلسة استماع): who runs it, and when it was closed (its minutes are printed once it is) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_actions', function (Blueprint $table) {
            $table->string('hearing_officer')->nullable()->after('hearing_location');
            $table->string('hearing_end_time', 5)->nullable()->after('hearing_officer'); // "HH:MM"
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_actions', function (Blueprint $table) {
            $table->dropColumn(['hearing_officer', 'hearing_end_time']);
        });
    }
};

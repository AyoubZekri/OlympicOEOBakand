<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who sent the justification: the member, or the administration for them (the member is told) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->string('justified_by')->nullable(); // member | administration
        });
    }

    public function down(): void
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->dropColumn('justified_by');
        });
    }
};

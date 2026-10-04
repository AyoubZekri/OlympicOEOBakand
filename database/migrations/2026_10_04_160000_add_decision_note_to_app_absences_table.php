<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Why the administration refused a justification or a request (the member reads it) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->text('decision_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_absences', function (Blueprint $table) {
            $table->dropColumn('decision_note');
        });
    }
};

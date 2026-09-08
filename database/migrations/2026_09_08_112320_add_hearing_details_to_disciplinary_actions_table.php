<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_actions', function (Blueprint $table) {
            $table->timestamp('deadline_or_hearing_date')->nullable();
            $table->string('hearing_location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_actions', function (Blueprint $table) {
            $table->dropColumn(['deadline_or_hearing_date', 'hearing_location']);
        });
    }
};

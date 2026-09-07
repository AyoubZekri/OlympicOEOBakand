<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_operations', function (Blueprint $table) {
            $table->string('sports_season')->nullable()->after('operation_date');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_operations', function (Blueprint $table) {
            $table->dropColumn('sports_season');
        });
    }
};

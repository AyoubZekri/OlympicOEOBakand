<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('formation')->nullable()->after('status');
        });

        Schema::table('match_callups', function (Blueprint $table) {
            $table->boolean('is_starter')->default(false)->after('notes');
            $table->decimal('position_x', 5, 2)->nullable()->after('is_starter');
            $table->decimal('position_y', 5, 2)->nullable()->after('position_x');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('formation');
        });

        Schema::table('match_callups', function (Blueprint $table) {
            $table->dropColumn(['is_starter', 'position_x', 'position_y']);
        });
    }
};

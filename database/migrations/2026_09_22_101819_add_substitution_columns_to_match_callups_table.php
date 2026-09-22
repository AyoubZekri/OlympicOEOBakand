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
        Schema::table('match_callups', function (Blueprint $table) {
            $table->integer('subbed_out_minute')->nullable()->after('is_starter');
            $table->foreignId('replaced_by_id')->nullable()->constrained('individuals')->onDelete('set null')->after('subbed_out_minute');
            $table->integer('subbed_in_minute')->nullable()->after('replaced_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_callups', function (Blueprint $table) {
            $table->dropForeign(['replaced_by_id']);
            $table->dropColumn(['subbed_out_minute', 'replaced_by_id', 'subbed_in_minute']);
        });
    }
};

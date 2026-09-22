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
            $table->integer('yellow_card_minute')->nullable();
            $table->integer('yellow_card_2_minute')->nullable();
            $table->integer('red_card_minute')->nullable();
            $table->string('red_card_type')->nullable(); // 'direct' or 'second_yellow'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_callups', function (Blueprint $table) {
            $table->dropColumn([
                'yellow_card_minute',
                'yellow_card_2_minute',
                'red_card_minute',
                'red_card_type'
            ]);
        });
    }
};

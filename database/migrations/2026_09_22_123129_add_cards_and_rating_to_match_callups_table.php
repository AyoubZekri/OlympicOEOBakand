<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('match_callups', function (Blueprint $table) {
            $table->integer('yellow_cards')->default(0)->after('position_y');
            $table->integer('red_cards')->default(0)->after('yellow_cards');
            $table->decimal('rating', 4, 1)->nullable()->after('red_cards');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('match_callups', function (Blueprint $table) {
            $table->dropColumn(['yellow_cards', 'red_cards', 'rating']);
        });
    }
};

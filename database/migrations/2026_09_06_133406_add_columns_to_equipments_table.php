<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->renameColumn('quantity', 'total_quantity');
            $table->integer('available_quantity')->nullable()->after('total_quantity');
            $table->string('image')->nullable()->after('available_quantity');
        });
    }

    public function down()
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn(['available_quantity', 'image']);
            $table->renameColumn('total_quantity', 'quantity');
        });
    }
};


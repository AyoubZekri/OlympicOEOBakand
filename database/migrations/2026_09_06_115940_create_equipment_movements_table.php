<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->nullable()->constrained('equipment_operations');
            $table->foreignId('equipment_id')->nullable()->constrained('equipments');
            $table->integer('quantity')->nullable();
            $table->string('movement_status')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->string('delivery_condition')->nullable();
            $table->timestamp('return_date')->nullable();
            $table->string('return_condition')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_movements');
    }
};

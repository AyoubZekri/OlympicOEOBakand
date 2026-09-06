<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipment_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('individuals');
            $table->timestamp('operation_date')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_operations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('department_meetings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('meeting_date')->nullable();
            $table->text('agenda')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('individuals');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('department_meetings');
    }
};

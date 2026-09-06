<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hearing_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->nullable()->constrained('disciplinary_actions');
            $table->foreignId('individuals_id')->nullable()->constrained('individuals');
            $table->string('individuals_role')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hearing_attendees');
    }
};

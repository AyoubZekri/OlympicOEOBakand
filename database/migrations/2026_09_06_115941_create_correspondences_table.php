<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('individuals');
            $table->foreignId('receiver_id')->nullable()->constrained('individuals');
            $table->string('receiver_role')->nullable();
            $table->string('message_type')->nullable();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->text('required_action')->nullable();
            $table->boolean('is_acknowledged')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('correspondences');
    }
};

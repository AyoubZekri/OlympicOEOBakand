<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The alerts already sent to a user's phone (one Firebase notification per alert, never twice) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_pushes');
    }
};

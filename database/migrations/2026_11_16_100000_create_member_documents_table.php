<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A member's other documents (beside the ID card, the medical certificate and the insurance): a name and its file */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_id')->constrained('individuals')->cascadeOnDelete();
            $table->string('name');
            $table->string('file');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The roles page's sections: a name each; a role sits in one or in none (a deleted section leaves its roles in none) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('permissions')->constrained('role_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });
        Schema::dropIfExists('role_sections');
    }
};

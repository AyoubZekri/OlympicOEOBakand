<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->string('incident_location')->nullable();
            $table->string('violated_rule')->nullable();
            $table->text('present_people')->nullable();
            $table->string('attachments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->dropColumn(['incident_location', 'violated_rule', 'present_people', 'attachments']);
        });
    }
};

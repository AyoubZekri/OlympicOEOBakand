<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A match's administrative report: the picture of the paper report, beside the written one */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administrative_match_reports', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('required_actions');
        });
    }

    public function down(): void
    {
        Schema::table('administrative_match_reports', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};

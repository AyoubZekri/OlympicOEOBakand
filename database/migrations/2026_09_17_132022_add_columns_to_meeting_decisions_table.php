<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('meeting_decisions', function (Blueprint $table) {
            $table->string('category')->nullable()->after('meeting_id');
            $table->string('type')->default('normal')->after('category');
            $table->json('checklist_items')->nullable()->after('type');
            $table->json('assignee_ids')->nullable()->after('decision_text');
            $table->integer('progress')->default(0)->after('deadline');
        });
    }

    public function down()
    {
        Schema::table('meeting_decisions', function (Blueprint $table) {
            $table->dropColumn(['category', 'type', 'checklist_items', 'assignee_ids', 'progress']);
        });
    }
};

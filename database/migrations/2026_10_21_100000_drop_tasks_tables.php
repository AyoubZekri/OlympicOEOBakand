<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tasks are removed from the app (to be rebuilt on a new plan): their tables and everything in them go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('task_status_history');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_templates');
        Schema::enableForeignKeyConstraints();
    }

    /** The tables come back empty, as the creation migration made them */
    public function down(): void
    {
        (include database_path('migrations/2026_09_29_100000_create_tasks_tables.php'))->up();
    }
};

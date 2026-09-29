<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task engine: tasks, the templates that generate them (periodic RRULE or system events),
 * proof attachments and the full status history (audit trail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('kind', 20); // periodic | event
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_proof')->default(false);
            // periodic: RRULE (FREQ, INTERVAL, BYDAY, BYMONTHDAY, BYHOUR, BYMINUTE, UNTIL) anchored on starts_on
            $table->string('rrule')->nullable();
            $table->dateTime('starts_on')->nullable();
            $table->dateTime('next_run_at')->nullable();
            // event: which system event creates the task, and when it is due relative to the event time
            $table->string('trigger', 40)->nullable(); // match.created | training.created | meeting.created
            $table->integer('offset_minutes')->default(0); // negative = before the event
            $table->unsignedInteger('duration_minutes')->default(1440); // time given to do the task
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_proof')->default(false);
            // assigned | in_progress | blocked | in_review | approved | returned (overdue is computed, never stored)
            $table->string('status', 20)->default('assigned');
            $table->string('block_reason', 30)->nullable();
            $table->text('block_note')->nullable();
            $table->text('return_reason')->nullable();
            $table->string('source_type', 20)->default('manual'); // manual | periodic | event
            $table->string('source_ref', 60)->nullable(); // e.g. match:12, training:4
            $table->foreignId('template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assignee_id', 'status']);
            $table->index(['reviewer_id', 'status']);
            $table->index('due_at');
            // A template creates each occurrence / each event's task only once
            $table->unique(['template_id', 'source_ref', 'starts_at'], 'tasks_template_occurrence_unique');
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 10); // file | image | text | link
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('url', 1000)->nullable();
            $table->text('body')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('task_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            // created | updated | started | blocked | resumed | attached | submitted | approved | returned | deleted | restored
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_history');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_templates');
    }
};

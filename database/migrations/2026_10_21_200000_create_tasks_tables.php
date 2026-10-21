<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tasks (new plan), in two tables:
 * - tasks: a task given to one member, and the "appointments" it comes from (is_template):
 *   linked to an event (every new match / training session / trip / meeting creates the task) or periodic (weekly, monthly);
 * - task_messages: the conversation about a task, with the status changes written in it.
 * Dates are the club's local time (Algeria), as the matches and the sessions store them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('details')->nullable();
            // normal | urgent (given at once) | scheduled (an appointment and the tasks it creates)
            $table->string('kind', 12);
            // internal | external | call
            $table->string('nature', 12);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // ── The appointment (is_template) ──
            $table->boolean('is_template')->default(false);
            // event | periodic
            $table->string('schedule', 10)->nullable();
            // event: match | training | travel | meeting
            $table->string('event_type', 10)->nullable();
            // periodic: weekly (1 = Monday … 7 = Sunday) | monthly (1 … 31, the month's last day when shorter)
            $table->string('repeat', 8)->nullable();
            $table->unsignedTinyInteger('repeat_day')->nullable();
            // The alert and the deadline, in minutes: from the event's time (negative = before), or from the periodic day's start
            $table->integer('alert_offset')->nullable();
            $table->integer('due_offset')->nullable();
            // periodic: the last day a task was created for
            $table->date('last_run_on')->nullable();

            // ── The task ──
            $table->foreignId('template_id')->nullable()->constrained('tasks')->nullOnDelete();
            // Where it comes from: "match:12", "day:2026-10-25" (one task per event / day and appointment)
            $table->string('source_ref', 40)->nullable();
            $table->string('event_label')->nullable();
            $table->dateTime('alert_at')->nullable();
            $table->dateTime('due_at')->nullable();
            // task: new | in_progress | on_hold | done | cancelled — appointment: active | paused
            $table->string('status', 12)->default('new');
            $table->timestamp('done_at')->nullable();
            // Read marks: the member opened the task / read the conversation; the managers read it
            $table->timestamp('assignee_seen_at')->nullable();
            $table->timestamp('assignee_read_at')->nullable();
            $table->timestamp('managers_read_at')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'source_ref']);
            $table->index(['is_template', 'status']);
        });

        Schema::create('task_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            // A status change written in the conversation (the new status), null for a message
            $table->string('status', 12)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_messages');
        Schema::dropIfExists('tasks');
    }
};

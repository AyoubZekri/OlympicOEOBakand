<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members' opinions: a suggestion, a complaint or a note sent from the personal space.
 * The management replies to each one; a suggestion is also rated (1 to 5 stars).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('individual_id')->nullable()->index();
            // suggestion | complaint | note
            $table->string('type', 20);
            $table->string('subject');
            $table->text('body')->nullable();
            $table->text('reply')->nullable();
            // Suggestions only: 1 → 5
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedBigInteger('replied_by')->nullable();
            $table->timestamp('replied_at')->nullable();
            // The member has seen the reply (their "new reply" mark goes away)
            $table->timestamp('reply_seen_at')->nullable();
            // The management has opened it (no longer "new")
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_feedback');
    }
};

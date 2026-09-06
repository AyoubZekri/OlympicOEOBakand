<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings');
            $table->text('decision_text')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('individuals');
            $table->date('deadline')->nullable();
            $table->string('execution_status')->nullable();
            $table->text('execution_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('meeting_decisions');
    }
};

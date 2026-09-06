<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individuals_id')->nullable()->constrained('individuals');
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamp('incident_date')->nullable();
            $table->string('incident_location')->nullable();
            $table->string('violated_rule')->nullable();
            $table->text('description')->nullable();
            $table->text('present_people')->nullable();
            $table->string('attachments')->nullable();
            $table->string('case_status')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('disciplinary_cases');
    }
};

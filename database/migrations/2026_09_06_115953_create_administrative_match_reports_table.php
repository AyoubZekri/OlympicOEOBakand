<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('administrative_match_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->nullable()->constrained('matches');
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->boolean('travel_as_planned')->nullable();
            $table->string('attendance_status')->nullable();
            $table->string('equipment_status')->nullable();
            $table->text('equipment_notes')->nullable();
            $table->text('accommodation_catering_notes')->nullable();
            $table->text('organizational_incidents')->nullable();
            $table->text('disciplinary_incidents')->nullable();
            $table->text('refereeing_notes')->nullable();
            $table->text('required_actions')->nullable();
            $table->timestamp('report_date')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('administrative_match_reports');
    }
};

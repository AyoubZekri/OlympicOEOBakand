<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('match_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->nullable()->constrained('matches');
            $table->string('match_result')->nullable();
            $table->string('bonus_type')->nullable();
            $table->decimal('base_amount')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('individuals');
            $table->foreignId('reviewed_by')->nullable()->constrained('individuals');
            $table->foreignId('approved_by')->nullable()->constrained('individuals');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('match_bonuses');
    }
};

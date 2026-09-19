<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('individuals', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('position')->nullable();
            $table->string('preferred_foot')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('national_id_document')->nullable();
            $table->string('medical_certificate')->nullable();
            $table->string('insurance_document')->nullable();
            $table->string('bank_account_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('individuals', function (Blueprint $table) {
            $table->dropColumn(['email', 'position', 'preferred_foot', 'emergency_contact_name', 'emergency_contact_phone', 'national_id_document', 'medical_certificate', 'insurance_document', 'bank_account_number']);
        });
    }
};

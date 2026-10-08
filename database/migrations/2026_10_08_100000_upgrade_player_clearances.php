<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clearance card in three steps: who started it, each department's note (a signature given
 * despite something still pending needs one), and the signers are users (their foreign keys
 * pointed at individuals, so a signature could be refused).
 */
return new class extends Migration
{
    private const SIGNERS = ['equipment_manager_id', 'sporting_director_id', 'finance_manager_id', 'medical_staff_id'];

    public function up(): void
    {
        foreach (self::SIGNERS as $column) {
            try {
                Schema::table('player_clearances', fn (Blueprint $table) => $table->dropForeign([$column]));
            } catch (\Throwable $e) {
                // No such foreign key on this database
            }
        }

        Schema::table('player_clearances', function (Blueprint $table) {
            $table->unsignedBigInteger('started_by')->nullable()->after('general_notes');
            $table->text('admin_notes')->nullable()->after('admin_cleared_at');
            $table->text('sporting_notes')->nullable()->after('sporting_cleared_at');
            $table->text('financial_notes')->nullable()->after('finance_cleared_at');
            $table->text('medical_notes')->nullable()->after('medical_cleared_at');
        });
    }

    public function down(): void
    {
        Schema::table('player_clearances', function (Blueprint $table) {
            $table->dropColumn(['started_by', 'admin_notes', 'sporting_notes', 'financial_notes', 'medical_notes']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A server that ran the first general_goals migration has its goals given to a member (individual_id): they are
 * given to a user (user_id) now. A database created after the change already has user_id and is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('general_goals', 'individual_id')) {
            Schema::table('general_goals', function (Blueprint $table) {
                $table->dropConstrainedForeignId('individual_id');
            });
        }

        if (! Schema::hasColumn('general_goals', 'user_id')) {
            Schema::table('general_goals', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('goal')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: the table's own migration owns user_id
    }
};

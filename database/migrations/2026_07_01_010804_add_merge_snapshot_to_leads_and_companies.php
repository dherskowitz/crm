<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores what a merge changed (moved child ids, added tags, backfilled
     * fields) on the loser row, so a merge can be reversed exactly.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('merge_snapshot')->nullable()->after('merged_into_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->json('merge_snapshot')->nullable()->after('merged_into_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('merge_snapshot');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('merge_snapshot');
        });
    }
};

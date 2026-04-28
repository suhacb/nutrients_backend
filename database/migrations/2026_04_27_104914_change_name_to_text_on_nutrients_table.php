<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the replacement index first so the source_id FK constraint
        // has a usable index before we drop the old three-column unique index.
        Schema::table('nutrients', function (Blueprint $table) {
            $table->unique(['source_id', 'external_id'], 'nutrients_source_id_external_id_unique');
        });

        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropUnique(['source_id', 'external_id', 'name']);
            $table->text('name')->change();
        });

        DB::statement('ALTER TABLE `nutrients` ADD INDEX `nutrients_name_prefix` (`name`(500))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `nutrients` DROP INDEX `nutrients_name_prefix`');

        // Change name back to varchar first; MySQL cannot add a full unique index on a text column.
        Schema::table('nutrients', function (Blueprint $table) {
            $table->string('name')->change();
        });

        // Add the 3-column unique index first (it covers source_id so the FK stays satisfied),
        // then drop the 2-column index in the same statement.
        Schema::table('nutrients', function (Blueprint $table) {
            $table->unique(['source_id', 'external_id', 'name']);
            $table->dropUnique('nutrients_source_id_external_id_unique');
        });
    }
};

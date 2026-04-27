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
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropUnique(['source_id', 'external_id', 'name']);
            $table->text('name')->change();
            $table->unique(['source_id', 'external_id'], 'nutrients_source_id_external_id_unique');
            $table->index(DB::raw('name(500)'), 'nutrients_name_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropIndex('nutrients_name_prefix');
            $table->dropUnique('nutrients_source_id_external_id_unique');
            $table->string('name')->change();
            $table->unique(['source_id', 'external_id', 'name']);
        });
    }
};

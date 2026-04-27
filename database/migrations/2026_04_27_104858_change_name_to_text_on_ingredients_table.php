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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id', 'name']);
            $table->text('name')->change();
            $table->unique(['source', 'external_id'], 'ingredients_source_external_id_unique');
            $table->index(DB::raw('name(500)'), 'ingredients_name_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex('ingredients_name_prefix');
            $table->dropUnique('ingredients_source_external_id_unique');
            $table->string('name')->change();
            $table->unique(['source', 'external_id', 'name']);
        });
    }
};

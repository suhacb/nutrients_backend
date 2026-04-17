<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id', 'name']);
            $table->dropColumn('source');

            $table->unsignedBigInteger('source_id')->after('id');
            $table->foreign('source_id')
                  ->references('id')->on('sources')
                  ->onDelete('restrict');
            $table->unique(['source_id', 'external_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropUnique(['source_id', 'external_id', 'name']);
            $table->dropColumn('source_id');

            $table->string('source')->after('id');
            $table->unique(['source', 'external_id', 'name']);
        });
    }
};

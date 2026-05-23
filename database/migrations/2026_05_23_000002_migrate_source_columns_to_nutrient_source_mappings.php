<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copy existing (source_id, external_id) pairs to the pivot table.
        // Only nutrients with a non-null external_id have a meaningful mapping row.
        DB::table('nutrients')
            ->whereNotNull('external_id')
            ->orderBy('id')
            ->chunk(500, function ($nutrients) {
                $rows = $nutrients->map(fn ($n) => [
                    'nutrient_id' => $n->id,
                    'source_id'   => $n->source_id,
                    'external_id' => $n->external_id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])->all();

                DB::table('nutrient_source_mappings')->insertOrIgnore($rows);
            });

        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropUnique('nutrients_source_id_external_id_unique');
            $table->dropColumn(['source_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('id');
            $table->string('external_id')->nullable()->after('source_id');

            $table->foreign('source_id')
                  ->references('id')->on('sources')
                  ->onDelete('restrict');

            $table->unique(['source_id', 'external_id'], 'nutrients_source_id_external_id_unique');
        });

        // Restore the first mapping per nutrient (first wins by id ascending).
        DB::table('nutrient_source_mappings')
            ->orderBy('id')
            ->chunk(500, function ($mappings) {
                foreach ($mappings as $mapping) {
                    DB::table('nutrients')
                        ->where('id', $mapping->nutrient_id)
                        ->whereNull('source_id')
                        ->update([
                            'source_id'   => $mapping->source_id,
                            'external_id' => $mapping->external_id,
                        ]);
                }
            });
    }
};

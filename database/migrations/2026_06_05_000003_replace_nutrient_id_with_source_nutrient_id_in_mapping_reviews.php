<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrient_mapping_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nutrient_id');

            $table->foreignId('source_nutrient_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('source_nutrients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nutrient_mapping_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_nutrient_id');

            $table->foreignId('nutrient_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('nutrients')
                ->nullOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrient_mapping_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('suggested_canonical_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('nutrient_mapping_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('suggested_canonical_id')->nullable(false)->change();
        });
    }
};

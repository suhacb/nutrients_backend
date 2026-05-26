<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_nutrition_facts', function (Blueprint $table) {
            $table->foreignId('nutrient_id')->nullable()->nullOnDelete()->constrained('nutrients')->after('amount_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_nutrition_facts', function (Blueprint $table) {
            $table->dropForeign(['nutrient_id']);
            $table->dropColumn('nutrient_id');
        });
    }
};

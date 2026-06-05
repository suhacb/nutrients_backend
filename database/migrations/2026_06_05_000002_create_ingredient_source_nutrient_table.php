<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_source_nutrient', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('source_nutrient_id')->constrained('source_nutrients')->cascadeOnDelete();
            $table->decimal('amount', 10, 4);
            $table->foreignId('amount_unit_id')->constrained('units');
            $table->timestamps();

            $table->primary(['ingredient_id', 'source_nutrient_id', 'amount_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_source_nutrient');
    }
};

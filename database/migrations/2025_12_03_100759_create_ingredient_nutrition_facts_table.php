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
        Schema::create('ingredient_nutrition_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->string('category'); // macro, micro, energy
            $table->string('name'); // e.g., Protein, Vitamin A, Calories
            $table->double('amount');
            $table->foreignId('amount_unit_id')->constrained('units');
            $table->timestamps();
            $table->unique(['ingredient_id', 'category', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_nutrition_facts');
    }
};

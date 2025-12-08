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
        Schema::create('ingredient_nutrient', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id');
            $table->unsignedBigInteger('nutrient_id');
            $table->float('amount');
            $table->unsignedBigInteger('amount_unit_id');
            $table->float('portion_amount')->nullable();
            $table->unsignedBigInteger('portion_amount_unit_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('nutrient_id')->references('id')->on('nutrients')->onDelete('cascade');
            $table->foreign('amount_unit_id')->references('id')->on('units')->onDelete('restrict');
            $table->foreign('portion_amount_unit_id')->references('id')->on('units')->onDelete('restrict');

            // Unique constraint to prevent duplicate pairs
            $table->unique(
                ['ingredient_id', 'nutrient_id', 'amount_unit_id'],
                'ing_nutr_amt_unit_unique' // <-- short unique index name
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_nutrient');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrient_nutrient_tag', function (Blueprint $table) {
            $table->foreignId('nutrient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nutrient_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['nutrient_id', 'nutrient_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrient_nutrient_tag');
    }
};

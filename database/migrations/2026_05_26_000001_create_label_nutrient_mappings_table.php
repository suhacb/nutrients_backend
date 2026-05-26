<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_nutrient_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('label_key', 50)->unique();
            $table->foreignId('nutrient_id')->constrained('nutrients')->cascadeOnDelete();
            $table->unsignedTinyInteger('confidence');
            $table->text('reasoning');
            $table->enum('status', ['pending', 'approved'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_nutrient_mappings');
    }
};

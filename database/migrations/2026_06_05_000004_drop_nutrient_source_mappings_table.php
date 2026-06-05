<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('nutrient_source_mappings');
    }

    public function down(): void
    {
        Schema::create('nutrient_source_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrient_id')->constrained('nutrients')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('source_name')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
        });
    }
};

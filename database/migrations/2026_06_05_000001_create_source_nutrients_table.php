<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('canonical_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('nutrient_id')->nullable()->constrained('nutrients')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index('nutrient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_nutrients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrient_mapping_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrient_id')->nullable()->unique()->constrained('nutrients')->nullOnDelete();
            $table->foreignId('suggested_canonical_id')->constrained('nutrients')->cascadeOnDelete();
            $table->unsignedTinyInteger('confidence');
            $table->enum('decision_type', ['merge', 'keep'])->default('merge');
            $table->text('reasoning');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrient_mapping_reviews');
    }
};

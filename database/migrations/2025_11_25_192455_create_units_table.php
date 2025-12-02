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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation');
            $table->string('type')->nullable();
            $table->timestamps();

            $table->unique(['name', 'type']);          // e.g., "ounce" + "mass" vs "ounce" + "volume"
            $table->unique(['abbreviation', 'type']);  // e.g., "oz" + "mass" vs "oz" + "volume"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};

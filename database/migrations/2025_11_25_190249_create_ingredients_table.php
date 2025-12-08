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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->string('source');
            $table->string('class')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->float('default_amount');
            $table->unsignedBigInteger('default_amount_unit_id');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source', 'external_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};

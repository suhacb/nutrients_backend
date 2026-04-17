<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->enum('type', ['mass', 'energy', 'volume', 'other'])->nullable()->change();
            $table->unsignedBigInteger('base_unit_id')->nullable()->after('type');
            $table->decimal('to_base_factor', 20, 10)->nullable()->after('base_unit_id');
            $table->foreign('base_unit_id')->references('id')->on('units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['base_unit_id']);
            $table->dropColumn(['base_unit_id', 'to_base_factor']);
            $table->string('type')->nullable()->change();
        });
    }
};

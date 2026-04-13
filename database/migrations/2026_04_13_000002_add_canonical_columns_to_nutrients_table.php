<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('description');
            $table->string('slug')->nullable()->unique()->after('parent_id');
            $table->unsignedBigInteger('canonical_unit_id')->nullable()->after('slug');
            $table->decimal('iu_to_canonical_factor', 12, 6)->nullable()->after('canonical_unit_id');
            $table->boolean('is_label_standard')->default(false)->after('iu_to_canonical_factor');
            $table->unsignedInteger('display_order')->nullable()->after('is_label_standard');

            $table->foreign('parent_id')
                  ->references('id')->on('nutrients')
                  ->onDelete('set null');

            $table->foreign('canonical_unit_id')
                  ->references('id')->on('units')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['canonical_unit_id']);
            $table->dropColumn([
                'parent_id',
                'slug',
                'canonical_unit_id',
                'iu_to_canonical_factor',
                'is_label_standard',
                'display_order',
            ]);
        });
    }
};

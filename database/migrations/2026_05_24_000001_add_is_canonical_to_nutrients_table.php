<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->boolean('is_canonical')->default(false)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropColumn('is_canonical');
        });
    }
};

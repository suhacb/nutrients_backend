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
        Schema::table('nutrients', function (Blueprint $table) {
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending')->after('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('nutrients', function (Blueprint $table) {
            $table->dropColumn('sync_status');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE nutrient_mapping_reviews MODIFY decision_type ENUM('merge', 'keep', 'parent') NOT NULL DEFAULT 'merge'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE nutrient_mapping_reviews MODIFY decision_type ENUM('merge', 'keep') NOT NULL DEFAULT 'merge'");
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->after('id')->unique();
            $table->string('fname')->after('uname')->nullable();
            $table->string('lname')->after('fname')->nullable();
            $table->uuid('external_id')->nullable()->unique()->after('id');

            // Indexes for faster lookups (optional but good practice)
            $table->index('email');
            $table->index('username');
            $table->index('external_id');

            $table->dropColumn('password');
            $table->dropColumn('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['username']);
            $table->dropIndex(['external_id']);

            $table->dropColumn([
                'external_id',
                'username',
                'fname',
                'lname',
            ]);

            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
        });
    }
};

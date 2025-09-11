<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test update users table migration
     */
    public function test_update_users_table(): void
    {
        /**
         * Testing migration 2025_09_10_190134_update_users_table.php to
         * check if uname, fname and lname fields are created correctly.
         */
        $this->assertTrue(Schema::hasColumns('users',
            [
                'uname',
                'fname',
                'lname'
            ],
            'Users table does not have the required columns'
        ));

        /**
         * Testing if uname field is set to unique and is not null.
         */
        
        // Grab index info from the sqlite database used for testing
        $indexes = DB::select("PRAGMA index_list('users')");
        $uniqueIndexes = collect($indexes)->filter(fn ($i) => $i->unique);

        $this->assertTrue(
            $uniqueIndexes->contains(fn ($i) => str_contains($i->name, 'uname')),
            'Email field does not have a unique index'
        );
    }
}

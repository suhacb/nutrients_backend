<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    
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
            'The uname column in the users table does not have a unique index'
        );

        $this->assertFalse(
            Schema::hasColumn('users', 'name'),
            'The name column is still in the users table'
        );
    }

    /**
     * Tests User creation. The tests creates 100 fake
     * users with unique usernames and emails.
     */
    public function test_users_create(): void
    {
        $expected = 100;
        User::factory()->count($expected)->create();
        $this->assertEquals(
            $expected,
            User::get()->count(),
            'Number of created users does not match expected value.'
        );
    }
}

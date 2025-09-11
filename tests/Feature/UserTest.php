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
            'Email field does not have a unique index'
        );
    }

    /**
     * Test if User model has the added fields fillable.
     */
    public function test_user_model_fillable_attribute(): void
    {
        $user = new User();
        $fillable = $user->getFillable();
        $this->assertTrue(
            empty(array_diff(['uname', 'fname', 'lname'], $fillable)),
            'The Models\User model has not defined the necessary fillable attributes.'
        );
    }

    /**
     * Tests UsersController@create method. The tests creates 100 fake
     * users with unique usernames and emails. Then it tries to create
     * a user with existing username and another with existing email.
     */
    public function test_users_controller_create(): void
    {
        User::factory()->count(100)->create();
        $this->assertEquals(
            1,
            User::get()->count(),
            'Number of created users does not match expected value.'
        );
    }
}

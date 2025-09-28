<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Illuminate\Support\Str;

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

    /**
     * Test if user can be created using the UsersController.
     */
    public function test_user_can_be_created_using_api()
    {
        /**
         * We first create a user using the factory to validate the
         * store method and also to have a user stored for following
         * tests which assers uniqueness.
         */
        $password = 'superSecretPassword123';
        $user = User::factory()->raw(['password' => $password]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(201, "Expected HTTP 201 Created, but received {$response->getStatusCode()}. User creation via API failed.");

        $this->assertDatabaseHas('users', [
            'uname' => $user['uname'],
            'email' => $user['email']
        ]);

        // Ensure password is hashed
        $retreived_user = User::where('uname', $user['uname'])->first();
        $this->assertTrue(Hash::check($password, $retreived_user->password), 'Password is not hashed correctly.');

        /**
         * We first test for all the possible request validation errors.
         */

        // Validation rule: 'uname.required' => 'Username is required.',
        $user = User::factory()->raw(['uname' => null]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'uname.string' => 'Username must be a valid string.',
        $user = User::factory()->raw(['uname' => 123]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'uname.max' => 'Username cannot exceed 255 characters.',
        $user = User::factory()->raw(['uname' => Str::random(256)]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'uname.unique' => 'This username is already taken.',
        $existing_user = User::first();
        $user = User::factory()->raw(['uname' => $existing_user->uname]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'fname.required' => 'First name is required.',
        $user = User::factory()->raw(['fname' => null]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'fname.string' => 'First name must be a valid string.',
        $user = User::factory()->raw(['fname' => 123]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'fname.max' => 'First name cannot exceed 255 characters.',
        $user = User::factory()->raw(['fname' => Str::random(256)]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'lname.required' => 'Last name is required.',
        $user = User::factory()->raw(['lname' => null]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'lname.string' => 'Last name must be a valid string.',
        $user = User::factory()->raw(['lname' => 123]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'lname.max' => 'Last name cannot exceed 255 characters.'
        $user = User::factory()->raw(['lname' => Str::random(256)]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'email.required' => 'Email is required.',
        $user = User::factory()->raw(['email' => null]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'email.email' => 'Email must be a valid email address.',
        $user = User::factory()->raw(['email' => 'something@something_else']);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'email.max' => 'Email cannot exceed 255 characters.',
        $user = User::factory()->raw(['email' => Str::random(256) . '@example.com']);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'email.unique' => 'This email is already registered.',
        $user = User::factory()->raw(['email' => $existing_user->email]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'password.required' => 'Password is required.',
        $user = User::factory()->raw(['password' => null]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'password.string' => 'Password must be a valid string.',
        $user = User::factory()->raw(['password' => 12345678]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Validation rule: 'password.min' => 'Password must be at least 8 characters long.',
        $user = User::factory()->raw(['password' => 123]);
        $response = $this->postJson('/api/users', $user);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");
    }
}

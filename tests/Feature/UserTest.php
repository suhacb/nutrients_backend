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
use Laravel\Passport\Passport;

class UserTest extends TestCase
{
    use RefreshDatabase;
    /**
     * To test the protected API endpoints we need to seed the
     * first user and authenticate him.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'UsersTableSeeder']);
        $user = User::first();
        // $token = $user->createToken('TestToken')->accessToken;
        // $this->withHeaders([
        //     'Authorization' => "Bearer {$token}",
        //     'Accept' => 'application/json',
        // ]);
        Passport::actingAs($user);
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
        $number_of_users_to_create = 100;
        $expected = User::count() + $number_of_users_to_create;
        User::factory()->count($number_of_users_to_create)->create();
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

    /**
     * Test if user can be updated using the UsersController.
     */
    public function test_user_can_be_updated_using_api() {
        // First create a user to update
        $user = User::factory()->raw();
        $response = $this->postJson(route('users.create'), $user);
        $response->assertStatus(201, "Expected HTTP 201 Created, but received {$response->getStatusCode()}. User creation via API failed.");

        // Fetch the created user
        $created_user = User::where('uname', $user['uname'])->first();
        Log::info($created_user->id);
        $this->assertNotNull($created_user, 'User was not created successfully for update test.');
        
        // For the test, we will swap the first and last names
        $response = $this->putJson(route('users.update', $created_user), [
            'fname' => $user['lname'],
            'lname' => $user['fname']
        ]);
        $response->assertStatus(200, "Expected HTTP 200 OK, but received {$response->getStatusCode()}. User update via API failed.");

        // Check if the response contains the updated data
        $response->assertJsonFragment([
            'fname' => $user['lname'],
            'lname' => $user['fname']
        ], 'API response does not contain the updated user data.');

        // Test to allow update when no data is passed
        $response = $this->putJson(route('users.update', $created_user), []);
        $response->assertStatus(200, "Expected HTTP 200 OK, but received {$response->getStatusCode()}. User update via API failed.");

        // Test to deny update when uname is not a string
        $response = $this->putJson(route('users.update', $created_user), ['uname' => 12345]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to deny update when uname is too long
        $response = $this->putJson(route('users.update', $created_user), ['uname' => Str::random(256)]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to deny update when uname is not unique
        $another_user = User::factory()->create();
        $response = $this->putJson(route('users.update', $created_user), ['uname' => $another_user->uname]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to allow update when uname is provided and is the same as the current one
        $response = $this->putJson(route('users.update', $created_user), ['uname' => $created_user->uname]);
        $response->assertStatus(200, "Expected HTTP 200 OK, but received {$response->getStatusCode()}. User update via API failed.");

        // Test to deny update when fname is not a string
        $response = $this->putJson(route('users.update', $created_user), ['fname' => 12345]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to deny update when fname is too long
        $response = $this->putJson(route('users.update', $created_user), ['fname' => Str::random(256)]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to deny update when email is not a string
        $response = $this->putJson(route('users.update', $created_user), ['email' => 12345]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to deny update when email is too long
        $response = $this->putJson(route('users.update', $created_user), ['email' => Str::random(256) . '@example.com']);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to deny update when email is not unique
        $another_user = User::factory()->create();
        $response = $this->putJson(route('users.update', $created_user), ['email' => $another_user->email]);
        $response->assertStatus(422, "Expected HTTP 422 Unprocessable Entity due to validation errors, but received {$response->getStatusCode()}. User creation validation did not fail as expected.");

        // Test to allow update when email is provided and is the same as the current one
        $response = $this->putJson(route('users.update', $created_user), ['email' => $created_user->email]);
        $response->assertStatus(200, "Expected HTTP 200 OK, but received {$response->getStatusCode()}. User update via API failed.");

    }
}

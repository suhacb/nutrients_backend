<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'uname' => 'testuser'
        ], [
            'fname' => 'Test',
            'lname' => 'User',
            'uname' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123', // always hash the password
        ]);
    }
}

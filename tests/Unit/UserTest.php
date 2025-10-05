<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * Test if User model has the added fields fillable.
     */
    public function test_user_model_fillable_attributes(): void
    {
        $user = new User();
        $fillable = $user->getFillable();
        $expected = ['uname', 'fname', 'lname', 'email', 'password'];
        $this->assertEqualsCanonicalizing($expected, $fillable, 'The Models\User model has not defined the necessary fillable attributes.');
    }
   
}

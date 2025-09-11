<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * Test setUname method.
     */
    public function test_set_uname_method(): void
    {
        $user = new User();
        $this->assertNull($user->uname);
        $user->fname = "Janez";
        $user->lname = "Novak";
        $user->setUname();
        $this->assertEquals('novakj', $user->uname);
    }
}

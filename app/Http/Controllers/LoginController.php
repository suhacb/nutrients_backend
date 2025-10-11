<?php

namespace App\Http\Controllers;

use App\Services\LoginRedirectService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    // public function __construct(LoginRedirectService $redirectService) {}

    public function login (Request $request) {
        return response()->json([
            'redirect_uri' => 'http://localhost:9020/login'
        ], 200);
    }
}

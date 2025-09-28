<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function store(UserRequest $request): JsonResponse
    {
        User::create([
            'uname' => $request->input('uname'),
            'fname' => $request->input('fname'),
            'lname' => $request->input('lname'),
            'email' => $request->input('email'),
            'password' => $request->input('password')
        ]);
        return response()->json(['message' => 'User created successfully'], 201);
    }
}

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

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());
        return response()->json(['message' => 'User updated successfully', 'data' => $user], 200);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $user], 200);
    }

    public function delete(User $user): JsonResponse
    {
        try {
            $user->delete();
            return response()->json(['message' => 'User deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete user'], 500);
        }
    }
}

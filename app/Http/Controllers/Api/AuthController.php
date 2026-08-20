<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (\Illuminate\Support\Facades\Auth::attempt($credentials)) {
            $user = clone \Illuminate\Support\Facades\Auth::user(); // For IDE typing
            $user = $request->user()->load('role');
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'User logged in successfully',
                'data' => [
                    'user' => $user,
                    'role' => $user->role,
                    'token' => $token
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid credentials'
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User logged out successfully'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load('role');
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'role' => $user->role
            ]
        ]);
    }
}

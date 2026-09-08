<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        /*
        |--------------------------------------------------------------------------
        | Verify credentials
        |--------------------------------------------------------------------------
        */
        if (
            !$user ||
            !Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent inactive accounts from logging in
        |--------------------------------------------------------------------------
        */
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Sanctum token
        |--------------------------------------------------------------------------
        */
        $token = $user->createToken(
            'api-token'
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}

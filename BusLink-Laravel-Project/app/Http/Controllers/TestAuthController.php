<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestAuthController extends Controller
{
    /**
     * Test login method
     */
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }
        
        // Using a direct token creation approach
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }
    
    /**
     * Test method to get user data
     */
    public function getUser(Request $request)
    {
        // Simple straightforward approach
        return response()->json([
            'user' => $request->user(),
            'token' => $request->bearerToken()
        ]);
    }
}

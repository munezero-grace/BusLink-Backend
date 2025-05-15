<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:passenger,driver',
            'phone_number' => 'required|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate a unique card number
        $cardNumber = 'BL-' . strtoupper(Str::random(8));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'card_number' => $cardNumber,
            'status' => 'active',
            'phone_number' => $request->phone_number,
        ]);

        // If user is a driver, create a driver profile
        if ($request->role === 'driver') {
            // Validate driver specific fields
            $driverValidator = Validator::make($request->all(), [
                'license_number' => 'required|string|max:50|unique:driver_profiles',
                'license_expiry' => 'required|date|after:today',
                'years_experience' => 'required|integer|min:0',
            ]);

            if ($driverValidator->fails()) {
                // Delete the user if driver validation fails
                $user->delete();
                return response()->json(['errors' => $driverValidator->errors()], 422);
            }

            DriverProfile::create([
                'user_id' => $user->id,
                'license_number' => $request->license_number,
                'license_expiry' => $request->license_expiry,
                'years_experience' => $request->years_experience,
                'performance_rating' => 0,
                'efficiency_score' => 0,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid login credentials'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Check if user is active
        if (!$user->isActive()) {
            return response()->json(['message' => 'Your account has been blocked. Please contact administrator.'], 403);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'driver') {
            $user->load('driverProfile');
        }
        
        return response()->json($user);
    }
}

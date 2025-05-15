<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class CustomTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get token from request
        $bearerToken = $request->bearerToken();
        
        if (!$bearerToken) {
            return response()->json(['message' => 'No token provided'], 401);
        }
        
        // Find the token in the database
        try {
            $token = PersonalAccessToken::findToken($bearerToken);
            
            if (!$token) {
                return response()->json(['message' => 'Invalid token'], 401);
            }
            
            // Get token's owner
            $user = $token->tokenable;
            
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
            
            // Set user in request
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            
            return $next($request);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== $role) {
            return response()->json(['message' => 'Access denied'], 403);
        }
        
        // Check if user is active
        if (!$user->isActive()) {
            return response()->json(['message' => 'Your account has been blocked'], 403);
        }

        return $next($request);
    }
}

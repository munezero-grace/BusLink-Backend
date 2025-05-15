<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get all passengers
     *
     * @return \Illuminate\Http\Response
     */
    public function passengers()
    {
        $passengers = User::where('role', 'passenger')->get();
        
        return response()->json($passengers);
    }
}

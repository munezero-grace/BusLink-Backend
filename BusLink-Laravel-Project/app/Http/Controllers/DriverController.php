<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /**
     * Get all drivers
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $drivers = User::where('role', 'driver')
            ->with('driverProfile')
            ->get();
            
        return response()->json($drivers);
    }

    /**
     * Toggle block/unblock a driver
     *
     * @param  \App\Models\User  $driver
     * @return \Illuminate\Http\Response
     */
    public function toggleBlock(User $driver)
    {
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $driver->status = $driver->status === 'active' ? 'blocked' : 'active';
        $driver->save();

        return response()->json([
            'message' => $driver->status === 'active' ? 'Driver unblocked successfully' : 'Driver blocked successfully',
            'driver' => $driver
        ]);
    }

    /**
     * Get authenticated driver's profile
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function profile(Request $request)
    {
        $driver = $request->user();
        
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $driver->load('driverProfile', 'driverProfile.car', 'route');
        
        return response()->json($driver);
    }

    /**
     * Get authenticated driver's performance
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function performance(Request $request)
    {
        $driver = $request->user();
        
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $driver->load('driverProfile', 'feedback');
        
        $performanceData = [
            'performance_rating' => $driver->driverProfile->performance_rating,
            'efficiency_score' => $driver->driverProfile->efficiency_score,
            'feedback_count' => $driver->feedback->count(),
            'average_rating' => $driver->feedback->avg('rating') ?? 0,
            'recent_feedback' => $driver->feedback()->latest()->take(5)->get(),
        ];
        
        return response()->json($performanceData);
    }

    /**
     * Get all drivers reported in passenger feedback.
     *
     * @return \Illuminate\Http\Response
     */
    public function reportedDrivers()
    {
        $reportedDrivers = User::where('role', 'driver')
            ->whereHas('feedbacks', function ($query) {
                $query->where('type', 'report');
            })
            ->with('feedbacks')
            ->get();

        return response()->json($reportedDrivers);
    }
}

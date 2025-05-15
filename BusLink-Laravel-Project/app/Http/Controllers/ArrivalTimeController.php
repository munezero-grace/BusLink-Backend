<?php

namespace App\Http\Controllers;

use App\Models\ArrivalTime;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ArrivalTimeController extends Controller
{
    /**
     * Display a listing of all arrival times.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ArrivalTime::with('driver');
        
        // Filter by date if provided
        if ($request->has('date')) {
            $query->where('check_in_date', $request->date);
        }
        
        // Filter by driver if provided
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $arrivalTimes = $query->latest('check_in_date')->get();
        
        return response()->json($arrivalTimes);
    }

    /**
     * Record driver's check-in.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   public function checkIn(Request $request)
{
    $driver = $request->user();

    if ($driver->role !== 'driver') {
        return response()->json(['message' => 'User is not a driver'], 400);
    }

    $today = now()->toDateString();
    $currentTime = now();
    $expectedTime = Carbon::createFromTime(8, 0, 0);
    $status = $currentTime->lt($expectedTime) ? 'on-time' : 'late';

    // Use updateOrCreate to allow one check-in per day
    $arrivalTime = ArrivalTime::updateOrCreate(
        [
            'driver_id' => $driver->id,
            'check_in_date' => $today,
        ],
        [
            'check_in_time' => $currentTime,
            'status' => $status,
        ]
    );

    return response()->json([
        'message' => 'Check-in recorded successfully',
        'arrival_time' => $arrivalTime,
    ], 201);
}
}
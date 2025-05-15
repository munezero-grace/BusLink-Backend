<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Display a listing of the bookings for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()->with('route')->get();
        
        return response()->json($bookings);
    }

    /**
     * Store a newly created booking in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:routes,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'seat_number' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the seat is already booked for this route and date
        $seatBooked = Booking::where('route_id', $request->route_id)
            ->where('booking_date', $request->booking_date)
            ->where('seat_number', $request->seat_number)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($seatBooked) {
            return response()->json(['message' => 'This seat is already booked'], 422);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'route_id' => $request->route_id,
            'seat_number' => $request->seat_number,
            'booking_date' => $request->booking_date,
            'status' => 'confirmed',
        ]);

        $booking->load('route');
        
        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking
        ], 201);
    }

    /**
     * Display the specified booking.
     *
     * @param  \App\Models\Booking  $booking
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Booking $booking, Request $request)
    {
        // Check if booking belongs to auth user
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $booking->load('route');
        
        return response()->json($booking);
    }

    /**
     * Cancel the specified booking.
     *
     * @param  \App\Models\Booking  $booking
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Booking $booking, Request $request)
    {
        // Check if booking belongs to auth user
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $booking->status = 'cancelled';
        $booking->save();
        
        return response()->json([
            'message' => 'Booking cancelled successfully'
        ]);
    }

    /**
     * Get bookings for driver's route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   public function driverBookings(Request $request)
{
    $driver = $request->user();

    if ($driver->role !== 'driver') {
        return response()->json(['message' => 'User is not a driver'], 400);
    }

    // First try to get route from relationship
    $route = $driver->route;

    if ($route) {
        // If driver has a direct route relationship
        $bookings = Booking::where('route_id', $route->id)
            ->where('booking_date', now()->toDateString())
            ->where('status', 'confirmed')
            ->with('user')
            ->get();
    } else {
        // Fallback: Find bookings by matching driver's ID in the route
        $bookings = Booking::whereHas('route', function ($query) use ($driver) {
                $query->where('driver_id', $driver->id);
            })
            ->where('booking_date', now()->toDateString())
            ->where('status', 'confirmed')
            ->with('user')
            ->get();
    }

    return response()->json($bookings);
}

    }


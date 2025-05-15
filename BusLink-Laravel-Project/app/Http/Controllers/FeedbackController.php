<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\User;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    /**
     * Display a listing of all feedback.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $feedback = Feedback::with(['user', 'driver', 'booking.route'])
            ->latest()
            ->get();
        
        return response()->json($feedback);
    }

    /**
     * Store a newly created feedback in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:users,id,role,driver',
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:500',
            'type' => 'required|string|in:feedback,claim,report',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if booking belongs to auth user
        $booking = $request->user()->bookings()->find($request->booking_id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found or does not belong to you'], 404);
        }

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'driver_id' => $request->driver_id,
            'booking_id' => $request->booking_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'type' => $request->type,
            'status' => 'pending',
        ]);

        // Update driver's performance rating
        $this->updateDriverRating($request->driver_id);
        
        return response()->json([
            'message' => 'Feedback submitted successfully',
            'feedback' => $feedback
        ], 201);
    }

    /**
     * Update the specified feedback in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Feedback  $feedback
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Feedback $feedback)
    {
        // Check if feedback belongs to auth user
        if ($feedback->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'sometimes|required|string|max:500',
            'type' => 'sometimes|required|string|in:feedback,claim,report',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback->update($request->only(['rating', 'comment', 'type']));

        // Update driver's performance rating
        $this->updateDriverRating($feedback->driver_id);

        return response()->json([
            'message' => 'Feedback updated successfully',
            'feedback' => $feedback
        ]);
    }

    /**
     * Remove the specified feedback from storage.
     *
     * @param  \App\Models\Feedback  $feedback
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Feedback $feedback, Request $request)
    {
        // Check if feedback belongs to auth user
        if ($feedback->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $driverId = $feedback->driver_id;
        $feedback->delete();

        // Update driver's performance rating
        $this->updateDriverRating($driverId);

        return response()->json([
            'message' => 'Feedback deleted successfully'
        ]);
    }

    /**
     * Get drivers who were reported in feedback.
     *
     * @return \Illuminate\Http\Response
     */
    public function reportedDrivers()
    {
        $drivers = User::where('role', 'driver')
            ->whereHas('feedback', function ($query) {
                $query->where('type', 'report');
            })
            ->with(['driverProfile', 'feedback' => function ($query) {
                $query->where('type', 'report')->with('user');
            }])
            ->get();
        
        return response()->json($drivers);
    }

    /**
     * Update driver's performance rating based on feedback.
     *
     * @param  int  $driverId
     * @return void
     */
    private function updateDriverRating($driverId)
    {
        $avgRating = Feedback::where('driver_id', $driverId)->avg('rating') ?? 0;
        
        $driverProfile = DriverProfile::where('user_id', $driverId)->first();
        if ($driverProfile) {
            $driverProfile->performance_rating = round($avgRating, 2);
            $driverProfile->save();
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Route;
use App\Models\Car;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    /**
     * Display a listing of all schedules.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Schedule::with(['route', 'car', 'driver']);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by day of week if provided
        if ($request->has('day')) {
            $query->whereJsonContains('days_of_week', strtolower($request->day));
        }
        
        $schedules = $query->get();
        
        return response()->json($schedules);
    }

    /**
     * Get schedules for the authenticated driver.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function driverSchedule(Request $request)
    {
        $driver = $request->user();
        
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }
        
        $schedules = Schedule::where('driver_id', $driver->id)
            ->where('status', 'active')
            ->with(['route', 'car'])
            ->get();
        
        return response()->json($schedules);
    }

    /**
     * Store a newly created schedule in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:routes,id',
            'car_id' => 'required|exists:cars,id',
            'driver_id' => 'required|exists:users,id,role,driver',
            'departure_time' => 'required|date_format:H:i',
            'arrival_time' => 'required|date_format:H:i|after:departure_time',
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'required|string|in:active,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if car is active
        $car = Car::find($request->car_id);
        if (!$car->isActive()) {
            return response()->json(['message' => 'Car is not active and cannot be scheduled'], 422);
        }

        // Check if driver is active
        $driver = User::find($request->driver_id);
        if ($driver->status !== 'active') {
            return response()->json(['message' => 'Driver is not active and cannot be scheduled'], 422);
        }

        // Check for conflicting schedules for the car
        $conflictingCarSchedules = $this->checkForConflicts($request->car_id, null, $request->departure_time, $request->arrival_time, $request->days_of_week);
        if ($conflictingCarSchedules->isNotEmpty()) {
            return response()->json(['message' => 'Car is already scheduled during this time on these days'], 422);
        }

        // Check for conflicting schedules for the driver
        $conflictingDriverSchedules = $this->checkForConflicts(null, $request->driver_id, $request->departure_time, $request->arrival_time, $request->days_of_week);
        if ($conflictingDriverSchedules->isNotEmpty()) {
            return response()->json(['message' => 'Driver is already scheduled during this time on these days'], 422);
        }

        $schedule = Schedule::create($request->all());
        $schedule->load(['route', 'car', 'driver']);
        
        return response()->json([
            'message' => 'Schedule created successfully',
            'schedule' => $schedule
        ], 201);
    }

    /**
     * Display the specified schedule.
     *
     * @param  \App\Models\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function show(Schedule $schedule)
    {
        $schedule->load(['route', 'car', 'driver', 'bookings.user']);
        
        return response()->json($schedule);
    }

    /**
     * Update the specified schedule in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Schedule $schedule)
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'sometimes|required|exists:routes,id',
            'car_id' => 'sometimes|required|exists:cars,id',
            'driver_id' => 'sometimes|required|exists:users,id,role,driver',
            'departure_time' => 'sometimes|required|date_format:H:i',
            'arrival_time' => 'sometimes|required|date_format:H:i|after:departure_time',
            'days_of_week' => 'sometimes|required|array|min:1',
            'days_of_week.*' => 'sometimes|required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'sometimes|required|string|in:active,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If car_id is changing, check if new car is active
        if (isset($request->car_id) && $request->car_id !== $schedule->car_id) {
            $car = Car::find($request->car_id);
            if (!$car->isActive()) {
                return response()->json(['message' => 'Car is not active and cannot be scheduled'], 422);
            }
            
            // Check for conflicting schedules for the new car
            $departure = $request->departure_time ?? $schedule->departure_time;
            $arrival = $request->arrival_time ?? $schedule->arrival_time;
            $days = $request->days_of_week ?? $schedule->days_of_week;
            
            $conflictingCarSchedules = $this->checkForConflicts($request->car_id, null, $departure, $arrival, $days, $schedule->id);
            if ($conflictingCarSchedules->isNotEmpty()) {
                return response()->json(['message' => 'Car is already scheduled during this time on these days'], 422);
            }
        }

        // If driver_id is changing, check if new driver is active
        if (isset($request->driver_id) && $request->driver_id !== $schedule->driver_id) {
            $driver = User::find($request->driver_id);
            if ($driver->status !== 'active') {
                return response()->json(['message' => 'Driver is not active and cannot be scheduled'], 422);
            }
            
            // Check for conflicting schedules for the new driver
            $departure = $request->departure_time ?? $schedule->departure_time;
            $arrival = $request->arrival_time ?? $schedule->arrival_time;
            $days = $request->days_of_week ?? $schedule->days_of_week;
            
            $conflictingDriverSchedules = $this->checkForConflicts(null, $request->driver_id, $departure, $arrival, $days, $schedule->id);
            if ($conflictingDriverSchedules->isNotEmpty()) {
                return response()->json(['message' => 'Driver is already scheduled during this time on these days'], 422);
            }
        }

        // If schedule time or days are changing, check for conflicts with existing car and driver
        if ((isset($request->departure_time) || isset($request->arrival_time) || isset($request->days_of_week)) &&
            (!isset($request->car_id) && !isset($request->driver_id))) {
            
            $departure = $request->departure_time ?? $schedule->departure_time;
            $arrival = $request->arrival_time ?? $schedule->arrival_time;
            $days = $request->days_of_week ?? $schedule->days_of_week;
            
            // Check car conflicts
            $conflictingCarSchedules = $this->checkForConflicts($schedule->car_id, null, $departure, $arrival, $days, $schedule->id);
            if ($conflictingCarSchedules->isNotEmpty()) {
                return response()->json(['message' => 'Car is already scheduled during this time on these days'], 422);
            }
            
            // Check driver conflicts
            $conflictingDriverSchedules = $this->checkForConflicts(null, $schedule->driver_id, $departure, $arrival, $days, $schedule->id);
            if ($conflictingDriverSchedules->isNotEmpty()) {
                return response()->json(['message' => 'Driver is already scheduled during this time on these days'], 422);
            }
        }

        $schedule->update($request->all());
        $schedule->load(['route', 'car', 'driver']);
        
        return response()->json([
            'message' => 'Schedule updated successfully',
            'schedule' => $schedule
        ]);
    }

    /**
     * Remove the specified schedule from storage.
     *
     * @param  \App\Models\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function destroy(Schedule $schedule)
    {
        // Check if schedule has any bookings
        if ($schedule->bookings()->exists()) {
            return response()->json(['message' => 'Cannot delete schedule with active bookings'], 422);
        }

        $schedule->delete();
        
        return response()->json([
            'message' => 'Schedule deleted successfully'
        ]);
    }

    /**
     * Change the status of a schedule.
     *
     * @param  \App\Models\Schedule  $schedule
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Schedule $schedule, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $schedule->status;
        $schedule->status = $request->status;
        $schedule->save();
        
        // Notify users if schedule is cancelled
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
            // Logic to notify users would go here (will be implemented in the notification system)
        }
        
        return response()->json([
            'message' => "Schedule status changed from {$oldStatus} to {$schedule->status} successfully",
            'schedule' => $schedule
        ]);
    }

    /**
     * Helper method to check for conflicting schedules.
     *
     * @param  int|null  $car_id
     * @param  int|null  $driver_id
     * @param  string  $departure_time
     * @param  string  $arrival_time
     * @param  array  $days_of_week
     * @param  int|null  $exclude_id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function checkForConflicts($car_id, $driver_id, $departure_time, $arrival_time, $days_of_week, $exclude_id = null)
    {
        $query = Schedule::where('status', 'active');
        
        if ($car_id) {
            $query->where('car_id', $car_id);
        }
        
        if ($driver_id) {
            $query->where('driver_id', $driver_id);
        }
        
        if ($exclude_id) {
            $query->where('id', '!=', $exclude_id);
        }
        
        // Time conflict check (departure during another schedule or arrival during another schedule)
        $query->where(function($q) use ($departure_time, $arrival_time) {
            $q->where(function($q1) use ($departure_time) {
                $q1->where('departure_time', '<=', $departure_time)
                   ->where('arrival_time', '>', $departure_time);
            })->orWhere(function($q2) use ($arrival_time) {
                $q2->where('departure_time', '<', $arrival_time)
                   ->where('arrival_time', '>=', $arrival_time);
            })->orWhere(function($q3) use ($departure_time, $arrival_time) {
                $q3->where('departure_time', '>=', $departure_time)
                   ->where('arrival_time', '<=', $arrival_time);
            });
        });
        
        // Days of week overlap check
        foreach ($days_of_week as $day) {
            $query->whereJsonContains('days_of_week', $day);
        }
        
        return $query->get();
    }
}

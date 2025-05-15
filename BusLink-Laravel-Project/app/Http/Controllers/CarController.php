<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class CarController extends Controller
{
    protected $notificationService;

    /**
     * Constructor
     *
     * @param  \App\Services\NotificationService  $notificationService
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of all cars/buses.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $cars = Car::with('driverProfile.user')->get();
        
        return response()->json($cars);
    }

    /**
     * Get car details for the authenticated driver.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function driverCar(Request $request)
    {
        $driver = $request->user();
        
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }
        
        // Get the driver profile
        $driverProfile = $driver->driverProfile;
        
        if (!$driverProfile || !$driverProfile->car_id) {
            return response()->json(['message' => 'No car assigned to this driver'], 404);
        }
        
        $car = Car::find($driverProfile->car_id);
        
        if (!$car) {
            return response()->json(['message' => 'Car not found'], 404);
        }
        
        return response()->json($car);
    }

    /**
     * Store a newly created car/bus in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plate_number' => 'required|string|max:20|unique:cars',
            'model' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'year' => 'required|integer|min:1990|max:'.date('Y'),
            'status' => 'required|string|in:active,maintenance,blocked',
            'features' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $car = Car::create($request->all());
        
        return response()->json([
            'message' => 'Bus/Car added successfully',
            'car' => $car
        ], 201);
    }

    /**
     * Display the specified car/bus.
     *
     * @param  \App\Models\Car  $car
     * @return \Illuminate\Http\Response
     */
    public function show(Car $car)
    {
        $car->load('driverProfile.user');
        
        return response()->json($car);
    }

    /**
     * Update the specified car/bus in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Car  $car
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Car $car)
    {
        $validator = Validator::make($request->all(), [
            'plate_number' => 'sometimes|required|string|max:20|unique:cars,plate_number,'.$car->id,
            'model' => 'sometimes|required|string|max:100',
            'capacity' => 'sometimes|required|integer|min:1',
            'year' => 'sometimes|required|integer|min:1990|max:'.date('Y'),
            'status' => 'sometimes|required|string|in:active,maintenance,blocked',
            'features' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $car->status;
        $car->update($request->all());
        
        // If status changed, notify the driver
        if (isset($request->status) && $oldStatus !== $request->status) {
            if ($car->driverProfile && $car->driverProfile->user) {
                $this->notificationService->sendCarStatusNotification($car, $request->status);
            }
        }
        
        return response()->json([
            'message' => 'Bus/Car updated successfully',
            'car' => $car
        ]);
    }

    /**
     * Remove the specified car/bus from storage.
     *
     * @param  \App\Models\Car  $car
     * @return \Illuminate\Http\Response
     */
    public function destroy(Car $car)
    {
        // Check if car is assigned to a driver
        if ($car->driverProfile()->exists()) {
            return response()->json(['message' => 'Cannot delete car/bus that is assigned to a driver'], 422);
        }

        // Check if car is used in any schedules
        if ($car->schedules()->exists()) {
            return response()->json(['message' => 'Cannot delete car/bus that is used in schedules'], 422);
        }

        $car->delete();
        
        return response()->json([
            'message' => 'Bus/Car deleted successfully'
        ]);
    }

    /**
     * Toggle the status of a car/bus (active/blocked/maintenance).
     *
     * @param  \App\Models\Car  $car
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toggleStatus(Car $car, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,maintenance,blocked',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $car->status;
        $car->status = $request->status;
        $car->save();
        
        // Notify the driver about status change
        if ($car->driverProfile && $car->driverProfile->user) {
            $this->notificationService->sendCarStatusNotification($car, $request->status);
        }
        
        return response()->json([
            'message' => "Bus/Car status changed from {$oldStatus} to {$car->status} successfully",
            'car' => $car
        ]);
    }
}

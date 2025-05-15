<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RouteController extends Controller
{
    /**
     * Google Maps Service
     * 
     * @var \App\Services\GoogleMapsService
     */
    protected $googleMapsService;

    /**
     * Constructor
     * 
     * @param \App\Services\GoogleMapsService $googleMapsService
     */
    public function __construct(GoogleMapsService $googleMapsService)
    {
        $this->googleMapsService = $googleMapsService;
    }
    /**
     * Display a listing of all routes.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $routes = Route::with('driver.driverProfile')->get();
        
        return response()->json($routes);
    }

    /**
     * Get the route assigned to the authenticated driver.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function driverRoute(Request $request)
    {
        $driver = $request->user();
        
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }
        
        $route = $driver->route;
        
        if (!$route) {
            return response()->json(['message' => 'No route assigned to this driver'], 404);
        }
        
        $route->load(['stations', 'schedules']);
        
        return response()->json($route);
    }

    /**
     * Store a newly created route in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_location' => 'required|string|max:255',
            'end_location' => 'required|string|max:255',
            'distance' => 'required|numeric|min:0',
            'estimated_time' => 'required|integer|min:1',
            'driver_id' => 'nullable|exists:users,id,role,driver',
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If driver_id is provided, check if driver already has a route
        if ($request->driver_id) {
            $driverHasRoute = Route::where('driver_id', $request->driver_id)->exists();
            if ($driverHasRoute) {
                return response()->json(['message' => 'Driver already assigned to a route'], 422);
            }
        }

        // Create route data
        $routeData = $request->all();

        // Get coordinates for start location using Google Maps API
        $startLocationGeocode = $this->googleMapsService->geocodeAddress($request->start_location);
        if ($startLocationGeocode['status'] === 'success') {
            $routeData['start_latitude'] = $startLocationGeocode['latitude'];
            $routeData['start_longitude'] = $startLocationGeocode['longitude'];
        }

        // Get coordinates for end location using Google Maps API
        $endLocationGeocode = $this->googleMapsService->geocodeAddress($request->end_location);
        if ($endLocationGeocode['status'] === 'success') {
            $routeData['end_latitude'] = $endLocationGeocode['latitude'];
            $routeData['end_longitude'] = $endLocationGeocode['longitude'];
        }

        // If both locations were geocoded successfully, calculate distance and time
        if (isset($routeData['start_latitude']) && isset($routeData['end_latitude'])) {
            $trafficInfo = $this->googleMapsService->getTrafficInfo(
                $routeData['start_latitude'],
                $routeData['start_longitude'],
                $routeData['end_latitude'],
                $routeData['end_longitude']
            );

            if ($trafficInfo['status'] === 'success') {
                // Override distance if provided by Google (convert from meters to km)
                if (!$request->has('distance') && isset($trafficInfo['distance']['value'])) {
                    $routeData['distance'] = round($trafficInfo['distance']['value'] / 1000, 2);
                }

                // Override estimated time if provided by Google (convert from seconds to minutes)
                if (!$request->has('estimated_time') && isset($trafficInfo['duration']['value'])) {
                    $routeData['estimated_time'] = ceil($trafficInfo['duration']['value'] / 60);
                }
            }
        }

        $route = Route::create($routeData);
        
        if ($request->driver_id) {
            $route->load('driver.driverProfile');
        }
        
        return response()->json([
            'message' => 'Route created successfully',
            'route' => $route
        ], 201);
    }

    /**
     * Display the specified route.
     *
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\Response
     */
    public function show(Route $route)
    {
        $route->load('driver.driverProfile');
        
        return response()->json($route);
    }

    /**
     * Update the specified route in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Route $route)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'start_location' => 'sometimes|required|string|max:255',
            'end_location' => 'sometimes|required|string|max:255',
            'distance' => 'sometimes|required|numeric|min:0',
            'estimated_time' => 'sometimes|required|integer|min:1',
            'driver_id' => 'sometimes|nullable|exists:users,id,role,driver',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If driver_id is changing and not null, check if new driver already has a route
        if (isset($request->driver_id) && $request->driver_id !== $route->driver_id && $request->driver_id !== null) {
            $driverHasRoute = Route::where('driver_id', $request->driver_id)->exists();
            if ($driverHasRoute) {
                return response()->json(['message' => 'Driver already assigned to a route'], 422);
            }
        }

        // Create route update data
        $routeData = $request->all();

        // If start location is changing, update coordinates
        if (isset($request->start_location) && $request->start_location !== $route->start_location) {
            $startLocationGeocode = $this->googleMapsService->geocodeAddress($request->start_location);
            if ($startLocationGeocode['status'] === 'success') {
                $routeData['start_latitude'] = $startLocationGeocode['latitude'];
                $routeData['start_longitude'] = $startLocationGeocode['longitude'];
            }
        }

        // If end location is changing, update coordinates
        if (isset($request->end_location) && $request->end_location !== $route->end_location) {
            $endLocationGeocode = $this->googleMapsService->geocodeAddress($request->end_location);
            if ($endLocationGeocode['status'] === 'success') {
                $routeData['end_latitude'] = $endLocationGeocode['latitude'];
                $routeData['end_longitude'] = $endLocationGeocode['longitude'];
            }
        }

        // If both locations have coordinates and either was updated, recalculate distance and time
        $startLat = $routeData['start_latitude'] ?? $route->start_latitude;
        $startLng = $routeData['start_longitude'] ?? $route->start_longitude;
        $endLat = $routeData['end_latitude'] ?? $route->end_latitude;
        $endLng = $routeData['end_longitude'] ?? $route->end_longitude;

        if ($startLat && $startLng && $endLat && $endLng && 
            (isset($routeData['start_latitude']) || isset($routeData['end_latitude']))) {
            
            $trafficInfo = $this->googleMapsService->getTrafficInfo(
                $startLat, $startLng, $endLat, $endLng
            );

            if ($trafficInfo['status'] === 'success') {
                // Update distance if not explicitly provided (convert from meters to km)
                if (!isset($routeData['distance']) && isset($trafficInfo['distance']['value'])) {
                    $routeData['distance'] = round($trafficInfo['distance']['value'] / 1000, 2);
                }

                // Update estimated time if not explicitly provided (convert from seconds to minutes)
                if (!isset($routeData['estimated_time']) && isset($trafficInfo['duration']['value'])) {
                    $routeData['estimated_time'] = ceil($trafficInfo['duration']['value'] / 60);
                }
            }
        }

        $route->update($routeData);
        
        if ($route->driver_id) {
            $route->load('driver.driverProfile');
        }
        
        return response()->json([
            'message' => 'Route updated successfully',
            'route' => $route
        ]);
    }

    /**
     * Remove the specified route from storage.
     *
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\Response
     */
    public function destroy(Route $route)
    {
        // Check if route has any bookings
        if ($route->bookings()->exists()) {
            return response()->json(['message' => 'Cannot delete route with active bookings'], 422);
        }

        $route->delete();
        
        return response()->json([
            'message' => 'Route deleted successfully'
        ]);
    }

    /**
     * Assign a driver to a route.
     *
     * @param  \App\Models\Route  $route
     * @param  \App\Models\User  $driver
     * @return \Illuminate\Http\Response
     */
    public function assignDriver(Route $route, User $driver)
    {
        // Check if user is a driver
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        // Check if driver is active
        if (!$driver->isActive()) {
            return response()->json(['message' => 'Driver is blocked and cannot be assigned'], 400);
        }

        // Check if driver already has a route
        $driverHasRoute = Route::where('driver_id', $driver->id)->where('id', '!=', $route->id)->exists();
        if ($driverHasRoute) {
            return response()->json(['message' => 'Driver already assigned to another route'], 422);
        }

        $route->driver_id = $driver->id;
        $route->save();
        
        $route->load('driver.driverProfile');
        
        return response()->json([
            'message' => 'Driver assigned to route successfully',
            'route' => $route
        ]);
    }
}

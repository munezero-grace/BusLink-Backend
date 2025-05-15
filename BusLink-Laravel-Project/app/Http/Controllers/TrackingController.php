<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Route;
use App\Services\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller
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
     * Update driver's location.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateLocation(Request $request)
    {
        $driver = $request->user();
        
        if ($driver->role !== 'driver') {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get or create driver profile
        $driverProfile = $driver->driverProfile ?? new DriverProfile(['user_id' => $driver->id]);
        
        $driverProfile->latitude = $request->latitude;
        $driverProfile->longitude = $request->longitude;
        
        // Use Google Maps Geocoding API to get address from lat/long
        $geocodeResult = $this->googleMapsService->geocodeReverse($request->latitude, $request->longitude);
        
        if ($geocodeResult['status'] === 'success') {
            $driverProfile->current_address = $geocodeResult['formatted_address'];
        } else {
            // If geocoding fails, just save the coordinates without an address
            $driverProfile->current_address = null;
        }
        
        $driverProfile->last_location_update = now();
        $driverProfile->save();
        
        // Check for route congestion if driver has an assigned route
        $congestionInfo = null;
        if ($driver->route) {
            $route = $driver->route;
            $destination = [
                'latitude' => $route->end_latitude,
                'longitude' => $route->end_longitude
            ];
            
            if ($destination['latitude'] && $destination['longitude']) {
                $trafficInfo = $this->googleMapsService->getTrafficInfo(
                    $request->latitude,
                    $request->longitude,
                    $destination['latitude'],
                    $destination['longitude']
                );
                
                if ($trafficInfo['status'] === 'success') {
                    $congestionInfo = [
                        'distance' => $trafficInfo['distance'],
                        'duration' => $trafficInfo['duration'],
                        'duration_in_traffic' => $trafficInfo['duration_in_traffic'],
                        'traffic_ratio' => $trafficInfo['duration_in_traffic']['value'] / $trafficInfo['duration']['value']
                    ];
                }
            }
        }
        
        return response()->json([
            'message' => 'Location updated successfully',
            'location' => [
                'latitude' => $driverProfile->latitude,
                'longitude' => $driverProfile->longitude,
                'address' => $driverProfile->current_address,
                'last_updated' => $driverProfile->last_location_update,
            ],
            'traffic' => $congestionInfo
        ]);
    }

    /**
     * Track a bus for a specific booking.
     *
     * @param  \App\Models\Booking  $booking
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function trackBus(Booking $booking, Request $request)
    {
        // Check if booking belongs to auth user
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if booking is confirmed
        if ($booking->status !== 'confirmed') {
            return response()->json(['message' => 'Booking is not active'], 400);
        }

        // Get the route and driver
        $route = $booking->route;
        if (!$route || !$route->driver_id) {
            return response()->json(['message' => 'No driver assigned to this route'], 404);
        }

        // Get driver's location
        $driverProfile = DriverProfile::where('user_id', $route->driver_id)->first();
        if (!$driverProfile || !$driverProfile->latitude || !$driverProfile->longitude) {
            return response()->json(['message' => 'Driver location not available'], 404);
        }

        // Get distance and time estimate to passenger's location (if provided)
        $estimatedArrival = null;
        if ($request->has('latitude') && $request->has('longitude')) {
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $trafficInfo = $this->googleMapsService->getTrafficInfo(
                $driverProfile->latitude,
                $driverProfile->longitude,
                $request->latitude,
                $request->longitude
            );

            if ($trafficInfo['status'] === 'success') {
                $estimatedArrival = [
                    'distance' => $trafficInfo['distance'],
                    'duration' => $trafficInfo['duration'],
                    'duration_in_traffic' => $trafficInfo['duration_in_traffic'],
                    'estimated_arrival_time' => now()->addSeconds($trafficInfo['duration_in_traffic']['value'])->format('Y-m-d H:i:s')
                ];
            }
        }

        // Get nearby stations if needed
        $nearbyStations = null;
        if ($request->has('include_stations') && $request->include_stations) {
            // Get user's position or use their provided position
            $latitude = $request->latitude ?? null;
            $longitude = $request->longitude ?? null;

            if ($latitude && $longitude) {
                $stationsResult = $this->googleMapsService->findNearbyPlaces(
                    $latitude,
                    $longitude,
                    'bus_station',
                    1 // 1km radius
                );

                if ($stationsResult['status'] === 'success') {
                    $nearbyStations = $stationsResult['places'];
                }
            }
        }

        // Get route stations
        $route->load('stations');

        return response()->json([
            'route' => $route,
            'driver_location' => [
                'latitude' => $driverProfile->latitude,
                'longitude' => $driverProfile->longitude,
                'address' => $driverProfile->current_address,
                'last_updated' => $driverProfile->updated_at,
            ],
            'estimated_arrival' => $estimatedArrival,
            'nearby_stations' => $nearbyStations
        ]);
    }

    /**
     * Find the best route based on traffic conditions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function findBestRoute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_latitude' => 'required|numeric|between:-90,90',
            'origin_longitude' => 'required|numeric|between:-180,180',
            'destination_latitude' => 'required|numeric|between:-90,90',
            'destination_longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get traffic info
        $trafficInfo = $this->googleMapsService->getTrafficInfo(
            $request->origin_latitude,
            $request->origin_longitude,
            $request->destination_latitude,
            $request->destination_longitude
        );

        if ($trafficInfo['status'] !== 'success') {
            return response()->json(['message' => 'Failed to get traffic information'], 500);
        }

        // Calculate traffic congestion level
        $trafficRatio = $trafficInfo['duration_in_traffic']['value'] / $trafficInfo['duration']['value'];
        $congestionLevel = 'low';
        
        if ($trafficRatio > 1.5) {
            $congestionLevel = 'high';
        } elseif ($trafficRatio > 1.2) {
            $congestionLevel = 'medium';
        }

        // Find routes that might be alternatives
        $alternativeRoutes = [];
        
        if ($congestionLevel !== 'low') {
            // In a real implementation, you might query your database for alternative routes
            // Here we're just providing the capability for the frontend to show alternatives
            $nearbyRoutes = Route::where('status', 'active')
                ->whereHas('stations', function ($query) use ($request) {
                    $query->whereRaw(
                        'ABS(latitude - ?) < 0.05 AND ABS(longitude - ?) < 0.05',
                        [$request->origin_latitude, $request->origin_longitude]
                    );
                })
                ->whereHas('stations', function ($query) use ($request) {
                    $query->whereRaw(
                        'ABS(latitude - ?) < 0.05 AND ABS(longitude - ?) < 0.05',
                        [$request->destination_latitude, $request->destination_longitude]
                    );
                })
                ->with(['stations' => function ($query) {
                    $query->orderBy('pivot_order');
                }])
                ->get();

            foreach ($nearbyRoutes as $route) {
                $alternativeRoutes[] = [
                    'id' => $route->id,
                    'name' => $route->name,
                    'start_location' => $route->start_location,
                    'end_location' => $route->end_location,
                    'estimated_time' => $route->estimated_time,
                    'stations_count' => $route->stations->count(),
                ];
            }
        }

        return response()->json([
            'traffic_info' => [
                'distance' => $trafficInfo['distance'],
                'normal_duration' => $trafficInfo['duration'],
                'duration_in_traffic' => $trafficInfo['duration_in_traffic'],
                'congestion_level' => $congestionLevel,
                'traffic_ratio' => round($trafficRatio, 2),
            ],
            'alternative_routes' => $alternativeRoutes,
            'polyline' => $trafficInfo['polyline'] ?? null,
        ]);
    }
}

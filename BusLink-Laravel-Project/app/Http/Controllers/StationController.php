<?php

namespace App\Http\Controllers;

use App\Models\Station;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StationController extends Controller
{
    /**
     * Display a listing of all stations.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $stations = Station::all();
        
        return response()->json($stations);
    }

    /**
     * Store a newly created station in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'description' => 'nullable|string',
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $station = Station::create($request->all());
        
        return response()->json([
            'message' => 'Station created successfully',
            'station' => $station
        ], 201);
    }

    /**
     * Display the specified station.
     *
     * @param  \App\Models\Station  $station
     * @return \Illuminate\Http\Response
     */
    public function show(Station $station)
    {
        $station->load('routes');
        
        return response()->json($station);
    }

    /**
     * Update the specified station in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Station  $station
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Station $station)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $station->update($request->all());
        
        return response()->json([
            'message' => 'Station updated successfully',
            'station' => $station
        ]);
    }

    /**
     * Remove the specified station from storage.
     *
     * @param  \App\Models\Station  $station
     * @return \Illuminate\Http\Response
     */
    public function destroy(Station $station)
    {
        // Check if station is used in any routes
        if ($station->routes()->exists()) {
            return response()->json(['message' => 'Cannot delete station that is used in routes'], 422);
        }

        $station->delete();
        
        return response()->json([
            'message' => 'Station deleted successfully'
        ]);
    }

    /**
     * Add a station to a route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\Response
     */
    public function addStationToRoute(Request $request, Route $route)
    {
        $validator = Validator::make($request->all(), [
            'station_id' => 'required|exists:stations,id',
            'order' => 'required|integer|min:1',
            'arrival_time' => 'nullable|date_format:H:i',
            'departure_time' => 'nullable|date_format:H:i|after_or_equal:arrival_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if station is already in the route
        if ($route->stations()->where('station_id', $request->station_id)->exists()) {
            return response()->json(['message' => 'Station is already in this route'], 422);
        }

        // If this is a new order number, we need to shift other stations
        if ($route->stations()->where('pivot_order', $request->order)->exists()) {
            // Shift all stations with order >= request order
            $route->stations()->wherePivot('order', '>=', $request->order)
                  ->update(['order' => \DB::raw('`order` + 1')]);
        }

        // Add station to route
        $route->stations()->attach($request->station_id, [
            'order' => $request->order,
            'arrival_time' => $request->arrival_time,
            'departure_time' => $request->departure_time,
        ]);

        $route->load('stations');
        
        return response()->json([
            'message' => 'Station added to route successfully',
            'route' => $route
        ]);
    }

    /**
     * Remove a station from a route.
     *
     * @param  \App\Models\Route  $route
     * @param  \App\Models\Station  $station
     * @return \Illuminate\Http\Response
     */
    public function removeStationFromRoute(Route $route, Station $station)
    {
        // Check if station is in the route
        if (!$route->stations()->where('station_id', $station->id)->exists()) {
            return response()->json(['message' => 'Station is not in this route'], 422);
        }

        // Get the order of the station being removed
        $orderToRemove = $route->stations()->where('station_id', $station->id)->first()->pivot->order;

        // Remove station from route
        $route->stations()->detach($station->id);

        // Reorder remaining stations
        $route->stations()->wherePivot('order', '>', $orderToRemove)
              ->update(['order' => \DB::raw('`order` - 1')]);

        $route->load('stations');
        
        return response()->json([
            'message' => 'Station removed from route successfully',
            'route' => $route
        ]);
    }

    /**
     * Update a station's information in a route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Route  $route
     * @param  \App\Models\Station  $station
     * @return \Illuminate\Http\Response
     */
    public function updateStationInRoute(Request $request, Route $route, Station $station)
    {
        $validator = Validator::make($request->all(), [
            'order' => 'sometimes|required|integer|min:1',
            'arrival_time' => 'nullable|date_format:H:i',
            'departure_time' => 'nullable|date_format:H:i|after_or_equal:arrival_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if station is in the route
        if (!$route->stations()->where('station_id', $station->id)->exists()) {
            return response()->json(['message' => 'Station is not in this route'], 422);
        }

        // Get current order
        $currentOrder = $route->stations()->where('station_id', $station->id)->first()->pivot->order;
        
        // If changing order, handle reordering
        if (isset($request->order) && $request->order != $currentOrder) {
            // First remove the station from its current position
            $route->stations()->updateExistingPivot($station->id, ['order' => 0]);
            
            // Reorder stations between old and new position
            if ($request->order > $currentOrder) {
                // Moving down: shift everything between current and new position up
                $route->stations()->wherePivot('order', '>', $currentOrder)
                      ->wherePivot('order', '<=', $request->order)
                      ->update(['order' => \DB::raw('`order` - 1')]);
            } else {
                // Moving up: shift everything between new and current position down
                $route->stations()->wherePivot('order', '>=', $request->order)
                      ->wherePivot('order', '<', $currentOrder)
                      ->update(['order' => \DB::raw('`order` + 1')]);
            }
        }

        // Update the station in the route
        $route->stations()->updateExistingPivot($station->id, [
            'order' => $request->order ?? $currentOrder,
            'arrival_time' => $request->arrival_time,
            'departure_time' => $request->departure_time,
        ]);

        $route->load('stations');
        
        return response()->json([
            'message' => 'Station in route updated successfully',
            'route' => $route
        ]);
    }
}

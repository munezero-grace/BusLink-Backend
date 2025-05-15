<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    /**
     * The Google Maps API key
     * 
     * @var string
     */
    protected $apiKey;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');
    }

    /**
     * Convert latitude and longitude to an address using Google Geocoding API
     *
     * @param  float  $latitude
     * @param  float  $longitude
     * @return array|null
     */
    public function geocodeReverse($latitude, $longitude)
    {
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$latitude},{$longitude}",
                'key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && $data['status'] === 'OK') {
                // Extract the formatted address and address components
                $formattedAddress = $data['results'][0]['formatted_address'] ?? null;
                $addressComponents = $data['results'][0]['address_components'] ?? [];
                
                // Extract specific address components
                $extractedComponents = $this->extractAddressComponents($addressComponents);
                
                return [
                    'status' => 'success',
                    'formatted_address' => $formattedAddress,
                    'address_components' => $extractedComponents,
                    'raw_data' => $data['results'][0] ?? null,
                ];
            } else {
                Log::warning('Google Geocoding API error', [
                    'status' => $data['status'] ?? 'Unknown',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
                
                return [
                    'status' => 'error',
                    'message' => $data['error_message'] ?? 'Geocoding failed: ' . ($data['status'] ?? 'Unknown error'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception in geocodeReverse', [
                'message' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            
            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert an address to latitude and longitude using Google Geocoding API
     *
     * @param  string  $address
     * @return array|null
     */
    public function geocodeAddress($address)
    {
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && $data['status'] === 'OK') {
                // Extract the location coordinates
                $location = $data['results'][0]['geometry']['location'] ?? null;
                
                return [
                    'status' => 'success',
                    'latitude' => $location['lat'] ?? null,
                    'longitude' => $location['lng'] ?? null,
                    'formatted_address' => $data['results'][0]['formatted_address'] ?? null,
                    'raw_data' => $data['results'][0] ?? null,
                ];
            } else {
                Log::warning('Google Geocoding API error', [
                    'status' => $data['status'] ?? 'Unknown',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                    'address' => $address,
                ]);
                
                return [
                    'status' => 'error',
                    'message' => $data['error_message'] ?? 'Geocoding failed: ' . ($data['status'] ?? 'Unknown error'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception in geocodeAddress', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);
            
            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get traffic information between two points
     *
     * @param  float  $originLat
     * @param  float  $originLng
     * @param  float  $destinationLat
     * @param  float  $destinationLng
     * @return array
     */
    public function getTrafficInfo($originLat, $originLng, $destinationLat, $destinationLng)
    {
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destinationLat},{$destinationLng}",
                'departure_time' => 'now',
                'traffic_model' => 'best_guess',
                'key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && $data['status'] === 'OK') {
                // Extract route information
                $route = $data['routes'][0] ?? null;
                $leg = $route['legs'][0] ?? null;
                
                if ($leg) {
                    return [
                        'status' => 'success',
                        'distance' => [
                            'text' => $leg['distance']['text'] ?? null,
                            'value' => $leg['distance']['value'] ?? null, // meters
                        ],
                        'duration' => [
                            'text' => $leg['duration']['text'] ?? null,
                            'value' => $leg['duration']['value'] ?? null, // seconds
                        ],
                        'duration_in_traffic' => [
                            'text' => $leg['duration_in_traffic']['text'] ?? null,
                            'value' => $leg['duration_in_traffic']['value'] ?? null, // seconds
                        ],
                        'start_address' => $leg['start_address'] ?? null,
                        'end_address' => $leg['end_address'] ?? null,
                        'steps' => $leg['steps'] ?? [],
                        'polyline' => $route['overview_polyline']['points'] ?? null,
                    ];
                }
            }
            
            Log::warning('Google Directions API error', [
                'status' => $data['status'] ?? 'Unknown',
                'error_message' => $data['error_message'] ?? 'No error message provided',
            ]);
            
            return [
                'status' => 'error',
                'message' => $data['error_message'] ?? 'Directions failed: ' . ($data['status'] ?? 'Unknown error'),
            ];
        } catch (\Exception $e) {
            Log::error('Exception in getTrafficInfo', [
                'message' => $e->getMessage(),
            ]);
            
            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract useful address components from Google's response
     *
     * @param  array  $components
     * @return array
     */
    protected function extractAddressComponents($components)
    {
        $result = [
            'street_number' => null,
            'route' => null,
            'neighborhood' => null,
            'locality' => null, // city
            'administrative_area_level_1' => null, // state/province
            'country' => null,
            'postal_code' => null,
        ];

        foreach ($components as $component) {
            $types = $component['types'] ?? [];
            
            if (in_array('street_number', $types)) {
                $result['street_number'] = $component['long_name'];
            } elseif (in_array('route', $types)) {
                $result['route'] = $component['long_name'];
            } elseif (in_array('neighborhood', $types)) {
                $result['neighborhood'] = $component['long_name'];
            } elseif (in_array('locality', $types)) {
                $result['locality'] = $component['long_name'];
            } elseif (in_array('administrative_area_level_1', $types)) {
                $result['administrative_area_level_1'] = $component['long_name'];
            } elseif (in_array('country', $types)) {
                $result['country'] = $component['long_name'];
            } elseif (in_array('postal_code', $types)) {
                $result['postal_code'] = $component['long_name'];
            }
        }

        return $result;
    }

    /**
     * Find nearest routes/stations based on current location
     * 
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @return array
     */
    public function findNearbyPlaces($latitude, $longitude, $type = 'bus_station', $radiusKm = 1)
    {
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
                'location' => "{$latitude},{$longitude}",
                'radius' => $radiusKm * 1000, // Convert to meters
                'type' => $type,
                'key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && $data['status'] === 'OK') {
                $places = [];
                
                foreach ($data['results'] as $result) {
                    $places[] = [
                        'name' => $result['name'],
                        'vicinity' => $result['vicinity'],
                        'latitude' => $result['geometry']['location']['lat'],
                        'longitude' => $result['geometry']['location']['lng'],
                        'place_id' => $result['place_id'],
                        'types' => $result['types'],
                    ];
                }
                
                return [
                    'status' => 'success',
                    'places' => $places,
                ];
            } else {
                Log::warning('Google Places API error', [
                    'status' => $data['status'] ?? 'Unknown',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                ]);
                
                return [
                    'status' => 'error',
                    'message' => $data['error_message'] ?? 'Nearby search failed: ' . ($data['status'] ?? 'Unknown error'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception in findNearbyPlaces', [
                'message' => $e->getMessage(),
            ]);
            
            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }
}

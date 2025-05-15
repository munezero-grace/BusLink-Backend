<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ArrivalTimeController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Basic test route
Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});

// Custom auth test route
Route::middleware('custom.auth')->get('/custom-user', function (Request $request) {
    return response()->json([
        'message' => 'Success! Authentication working with custom middleware',
        'user' => $request->user()
    ]);
});

// Test authentication routes
Route::post('/login', [\App\Http\Controllers\TestAuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/user', [\App\Http\Controllers\TestAuthController::class, 'getUser']);

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Notification routes for all users
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);
    
    // Admin routes
    Route::middleware('role:admin')->group(function () {
        // Driver management
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::patch('/drivers/{driver}/toggle-block', [DriverController::class, 'toggleBlock']);
        
        // Passenger management
        Route::get('/passengers', [UserController::class, 'passengers']);
        
        // Feedback management
        Route::get('/feedback', [FeedbackController::class, 'index']);
        Route::get('/feedback/reported-drivers', [FeedbackController::class, 'reportedDrivers']);
        
        // Route management
        Route::apiResource('/routes', RouteController::class);
        Route::post('/routes/{route}/assign-driver/{driver}', [RouteController::class, 'assignDriver']);
        
        // Arrival time management
        Route::get('/arrival-times', [ArrivalTimeController::class, 'index']);
        
        // Car/Bus management
        Route::apiResource('/cars', CarController::class);
        Route::patch('/cars/{car}/status', [CarController::class, 'toggleStatus']);
        
        // Schedule management
        Route::apiResource('/schedules', ScheduleController::class);
        Route::patch('/schedules/{schedule}/status', [ScheduleController::class, 'changeStatus']);
        
        // Station management
        Route::apiResource('/stations', StationController::class);
        Route::post('/routes/{route}/stations', [StationController::class, 'addStationToRoute']);
        Route::put('/routes/{route}/stations/{station}', [StationController::class, 'updateStationInRoute']);
        Route::delete('/routes/{route}/stations/{station}', [StationController::class, 'removeStationFromRoute']);
        
        // Reports & Analytics
        Route::get('/reports/dashboard', [ReportController::class, 'dashboardStats']);
        Route::get('/reports/passengers', [ReportController::class, 'passengerActivity']);
        Route::get('/reports/drivers', [ReportController::class, 'driverPerformance']);
        Route::get('/reports/routes', [ReportController::class, 'routeAnalysis']);
        Route::get('/reports/cars', [ReportController::class, 'carUtilization']);
        Route::get('/reports/custom', [ReportController::class, 'customReport']);
        
        // Notifications
        Route::post('/notifications', [NotificationController::class, 'store']);
    });
    
    // Driver routes
    Route::middleware('role:driver')->group(function () {
        Route::get('/profile', [DriverController::class, 'profile']);
        Route::get('/performance', [DriverController::class, 'performance']);
        Route::get('/bookings', [BookingController::class, 'driverBookings']);
        Route::post('/location', [TrackingController::class, 'updateLocation']);
        Route::post('/arrival', [ArrivalTimeController::class, 'checkIn']);
        
        // Route info for driver
        Route::get('/my-route', [RouteController::class, 'driverRoute']);
        Route::get('/my-schedule', [ScheduleController::class, 'driverSchedule']);
        
        // Car/bus details for driver
        Route::get('/my-car', [CarController::class, 'driverCar']);
    });
    
    // Passenger routes
    Route::middleware('role:passenger')->group(function () {
        Route::apiResource('/bookings', BookingController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('/feedback', FeedbackController::class)->only(['store', 'update', 'destroy']);
        // Traffic and Navigation
        Route::get('/track/{booking}', [TrackingController::class, 'trackBus']);
        Route::get('/best-route', [TrackingController::class, 'findBestRoute']);
    });
});

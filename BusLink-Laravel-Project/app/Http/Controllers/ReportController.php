<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\Route;
use App\Models\User;
use App\Models\Car;
use App\Models\Schedule;
use App\Models\ArrivalTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get dashboard overview statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function dashboardStats()
    {
        $stats = [
            'total_passengers' => User::where('role', 'passenger')->count(),
            'total_drivers' => User::where('role', 'driver')->count(),
            'total_cars' => Car::count(),
            'active_routes' => Route::where('status', 'active')->count(),
            'today_bookings' => Booking::whereDate('booking_date', Carbon::today())->count(),
            'total_bookings' => Booking::count(),
            'average_rating' => round(Feedback::avg('rating') ?? 0, 1),
        ];
        
        return response()->json($stats);
    }

    /**
     * Get passenger activity report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function passengerActivity(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::today();

        // Get daily booking counts
        $bookingsByDate = Booking::whereBetween('booking_date', [$startDate, $endDate])
            ->select(DB::raw('DATE(booking_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Get most popular routes
        $popularRoutes = Booking::whereBetween('booking_date', [$startDate, $endDate])
            ->select('route_id', DB::raw('COUNT(*) as bookings'))
            ->with('route:id,name')
            ->groupBy('route_id')
            ->orderByDesc('bookings')
            ->limit(5)
            ->get();
        
        // Get booking status distribution
        $bookingStatus = Booking::whereBetween('booking_date', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        
        return response()->json([
            'booking_trends' => $bookingsByDate,
            'popular_routes' => $popularRoutes,
            'booking_status' => $bookingStatus,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Get driver performance report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function driverPerformance(Request $request)
    {
        // Get top rated drivers
        $topDrivers = User::where('role', 'driver')
            ->with('driverProfile')
            ->withCount('feedback')
            ->withAvg('feedback as average_rating', 'rating')
            ->orderByDesc('average_rating')
            ->limit(10)
            ->get();
        
        // Get driver arrival statistics
        $arrivalStats = ArrivalTime::select('user_id', 
                DB::raw('COUNT(*) as total_days'),
                DB::raw('SUM(CASE WHEN status = "on_time" THEN 1 ELSE 0 END) as on_time'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN status = "very_late" THEN 1 ELSE 0 END) as very_late'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get();
        
        // Get driver efficiency scores
        $efficiencyScores = User::where('role', 'driver')
            ->with('driverProfile:user_id,efficiency_score,performance_rating')
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'efficiency_score' => $driver->driverProfile->efficiency_score ?? 0,
                    'performance_rating' => $driver->driverProfile->performance_rating ?? 0,
                ];
            });
        
        return response()->json([
            'top_drivers' => $topDrivers,
            'arrival_statistics' => $arrivalStats,
            'efficiency_scores' => $efficiencyScores,
        ]);
    }

    /**
     * Get route analysis report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function routeAnalysis(Request $request)
    {
        // Get booking counts per route
        $routeUsage = Route::withCount('bookings')
            ->orderByDesc('bookings_count')
            ->get();
        
        // Get routes with most feedback/complaints
        $routeFeedback = Route::withCount(['bookings.feedback as feedback_count' => function ($query) {
                $query->whereNotNull('comment');
            }])
            ->orderByDesc('feedback_count')
            ->limit(10)
            ->get();
        
        // Get estimated vs actual travel times (if tracking data is available)
        // This is a placeholder for future implementation
        $travelTimeAnalysis = [];
        
        return response()->json([
            'route_usage' => $routeUsage,
            'route_feedback' => $routeFeedback,
            'travel_time_analysis' => $travelTimeAnalysis,
        ]);
    }

    /**
     * Get car/bus utilization report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function carUtilization(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::today();

        // Get car status distribution
        $carStatus = Car::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        
        // Get car utilization via schedules (times a car is scheduled)
        $carSchedules = Schedule::whereBetween('created_at', [$startDate, $endDate])
            ->select('car_id', DB::raw('COUNT(*) as schedule_count'))
            ->with('car:id,plate_number,model')
            ->groupBy('car_id')
            ->orderByDesc('schedule_count')
            ->get();
        
        // Car age analysis
        $carAge = Car::select(
                DB::raw('YEAR(NOW()) - year as age'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('age')
            ->orderBy('age')
            ->get();
        
        return response()->json([
            'car_status' => $carStatus,
            'car_schedules' => $carSchedules,
            'car_age' => $carAge,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Generate a custom report based on specified parameters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function customReport(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::today();
        
        $reportData = [];
        
        // Include booking data if requested
        if ($request->include_bookings) {
            $bookingQuery = Booking::whereBetween('booking_date', [$startDate, $endDate]);
            
            if ($request->route_id) {
                $bookingQuery->where('route_id', $request->route_id);
            }
            
            if ($request->status) {
                $bookingQuery->where('status', $request->status);
            }
            
            $reportData['bookings'] = $bookingQuery->with(['user:id,name', 'route:id,name'])->get();
        }
        
        // Include feedback data if requested
        if ($request->include_feedback) {
            $feedbackQuery = Feedback::whereBetween('created_at', [$startDate, $endDate]);
            
            if ($request->min_rating) {
                $feedbackQuery->where('rating', '>=', $request->min_rating);
            }
            
            if ($request->max_rating) {
                $feedbackQuery->where('rating', '<=', $request->max_rating);
            }
            
            $reportData['feedback'] = $feedbackQuery->with(['user:id,name', 'driver:id,name'])->get();
        }
        
        // Include driver data if requested
        if ($request->include_drivers) {
            $driverQuery = User::where('role', 'driver');
            
            if ($request->driver_status) {
                $driverQuery->where('status', $request->driver_status);
            }
            
            $reportData['drivers'] = $driverQuery->with('driverProfile')->get();
        }
        
        // Include car data if requested
        if ($request->include_cars) {
            $carQuery = Car::query();
            
            if ($request->car_status) {
                $carQuery->where('status', $request->car_status);
            }
            
            $reportData['cars'] = $carQuery->with('driverProfile.user')->get();
        }
        
        return response()->json([
            'report_data' => $reportData,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'parameters' => $request->all(),
        ]);
    }
}

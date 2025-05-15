<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Route;
use App\Models\Car;

class NotificationService
{
    /**
     * Send notification to a single user.
     *
     * @param  int  $userId
     * @param  string  $title
     * @param  string  $message
     * @param  string  $type
     * @param  array|null  $data
     * @param  string|null  $link
     * @return \App\Models\Notification|false
     */
    public function sendToUser($userId, $title, $message, $type, $data = null, $link = null)
    {
        try {
            return Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
                'link' => $link,
            ]);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Failed to send notification to user: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users.
     *
     * @param  array  $userIds
     * @param  string  $title
     * @param  string  $message
     * @param  string  $type
     * @param  array|null  $data
     * @param  string|null  $link
     * @return array
     */
    public function sendToUsers($userIds, $title, $message, $type, $data = null, $link = null)
    {
        $results = [
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($userIds as $userId) {
            $notification = $this->sendToUser($userId, $title, $message, $type, $data, $link);
            if ($notification) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Send notification to all users with a specific role.
     *
     * @param  string  $role
     * @param  string  $title
     * @param  string  $message
     * @param  string  $type
     * @param  array|null  $data
     * @param  string|null  $link
     * @return array
     */
    public function sendToRole($role, $title, $message, $type, $data = null, $link = null)
    {
        $users = User::where('role', $role)->where('status', 'active')->get();
        $userIds = $users->pluck('id')->toArray();
        
        return $this->sendToUsers($userIds, $title, $message, $type, $data, $link);
    }

    /**
     * Send notification about a booking.
     *
     * @param  \App\Models\Booking  $booking
     * @param  string  $action
     * @return \App\Models\Notification|false
     */
    public function sendBookingNotification(Booking $booking, $action)
    {
        $user = $booking->user;
        $route = $booking->route;
        
        $title = '';
        $message = '';
        $type = 'booking';
        
        switch ($action) {
            case 'created':
                $title = 'Booking Confirmed';
                $message = "Your booking for route {$route->name} on {$booking->booking_date} has been confirmed.";
                break;
            case 'cancelled':
                $title = 'Booking Cancelled';
                $message = "Your booking for route {$route->name} on {$booking->booking_date} has been cancelled.";
                break;
            case 'completed':
                $title = 'Trip Completed';
                $message = "Your trip on route {$route->name} on {$booking->booking_date} has been marked as completed.";
                break;
            default:
                $title = 'Booking Update';
                $message = "There is an update to your booking for route {$route->name} on {$booking->booking_date}.";
        }
        
        return $this->sendToUser(
            $user->id,
            $title,
            $message,
            $type,
            ['booking_id' => $booking->id, 'action' => $action],
            "/bookings/{$booking->id}"
        );
    }

    /**
     * Send notification about a schedule change.
     *
     * @param  \App\Models\Schedule  $schedule
     * @param  string  $action
     * @return array
     */
    public function sendScheduleNotification(Schedule $schedule, $action)
    {
        $route = $schedule->route;
        $bookings = Booking::where('route_id', $route->id)
            ->where('booking_date', '>=', now())
            ->where('status', 'confirmed')
            ->get();
        
        $userIds = $bookings->pluck('user_id')->unique()->toArray();
        
        $title = '';
        $message = '';
        $type = 'schedule';
        
        switch ($action) {
            case 'cancelled':
                $title = 'Schedule Cancelled';
                $message = "The schedule for route {$route->name} has been cancelled.";
                break;
            case 'changed':
                $title = 'Schedule Changed';
                $message = "The schedule for route {$route->name} has been updated.";
                break;
            case 'delayed':
                $title = 'Schedule Delayed';
                $message = "The schedule for route {$route->name} is experiencing delays.";
                break;
            default:
                $title = 'Schedule Update';
                $message = "There is an update to the schedule for route {$route->name}.";
        }
        
        return $this->sendToUsers(
            $userIds,
            $title,
            $message,
            $type,
            ['schedule_id' => $schedule->id, 'action' => $action],
            "/schedules/{$schedule->id}"
        );
    }

    /**
     * Send notification about car status change (mainly to drivers).
     *
     * @param  \App\Models\Car  $car
     * @param  string  $newStatus
     * @return \App\Models\Notification|false
     */
    public function sendCarStatusNotification(Car $car, $newStatus)
    {
        $driverProfile = $car->driverProfile;
        
        if (!$driverProfile) {
            return false;
        }
        
        $driver = $driverProfile->user;
        
        $title = 'Car Status Changed';
        $message = "Your car ({$car->plate_number}) status has been changed to '{$newStatus}'.";
        
        if ($newStatus === 'maintenance') {
            $message .= ' The car will be temporarily unavailable.';
        } elseif ($newStatus === 'blocked') {
            $message .= ' Please contact administration for more information.';
        }
        
        return $this->sendToUser(
            $driver->id,
            $title,
            $message,
            'car_status',
            ['car_id' => $car->id, 'status' => $newStatus],
            null
        );
    }

    /**
     * Notify users about traffic congestion on a route.
     *
     * @param  \App\Models\Route  $route
     * @param  string  $message
     * @param  array|null  $affectedAreas
     * @return array
     */
    public function sendTrafficCongestionNotification(Route $route, $message, $affectedAreas = null)
    {
        $bookings = Booking::where('route_id', $route->id)
            ->where('booking_date', '>=', now())
            ->where('status', 'confirmed')
            ->get();
        
        $userIds = $bookings->pluck('user_id')->unique()->toArray();
        
        // Also notify the driver
        if ($route->driver_id) {
            $userIds[] = $route->driver_id;
        }
        
        return $this->sendToUsers(
            $userIds,
            'Traffic Alert',
            $message,
            'traffic',
            ['route_id' => $route->id, 'affected_areas' => $affectedAreas],
            "/routes/{$route->id}"
        );
    }
}

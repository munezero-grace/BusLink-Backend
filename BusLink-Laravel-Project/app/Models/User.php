<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'card_number',
        'status',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Check if user has a specific role
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if user is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Get the driver profile associated with the user.
     */
    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * Get the bookings made by the user.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the feedback given by the user.
     */
    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the assigned route for driver.
     */
    public function route()
    {
        return $this->hasOne(Route::class, 'driver_id');
    }

    /**
     * Get the arrival times for driver.
     */
    public function arrivalTimes()
    {
        return $this->hasMany(ArrivalTime::class, 'driver_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    
    /**
     * Get the unread notifications for the user.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }
}

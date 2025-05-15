<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'route_id',
        'car_id',
        'driver_id',
        'departure_time',
        'arrival_time',
        'days_of_week', // Stored as JSON, e.g. ["monday", "wednesday", "friday"]
        'status' // active, cancelled, completed
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'days_of_week' => 'array'
    ];

    /**
     * Get the route associated with the schedule.
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get the car associated with the schedule.
     */
    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Get the driver associated with the schedule.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the bookings for this schedule.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

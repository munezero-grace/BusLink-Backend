<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_location',
        'start_latitude',
        'start_longitude',
        'end_location',
        'end_latitude',
        'end_longitude',
        'distance',
        'estimated_time',
        'driver_id',
        'status', // active, inactive
    ];

    /**
     * Get the driver assigned to this route.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the bookings for this route.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plate_number',
        'model',
        'capacity',
        'year',
        'status', // active, maintenance, blocked
        'features'
    ];

    /**
     * Get the driver profile associated with the car.
     */
    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * Get the schedules for this car.
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Check if car is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }
}

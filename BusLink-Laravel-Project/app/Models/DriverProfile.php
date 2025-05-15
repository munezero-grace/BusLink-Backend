<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'license_number',
        'license_expiry',
        'years_experience',
        'performance_rating',
        'efficiency_score',
        'car_id',
        'latitude',
        'longitude',
        'current_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'license_expiry' => 'date',
        'performance_rating' => 'float',
        'efficiency_score' => 'float',
    ];

    /**
     * Get the user that owns the driver profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the car assigned to this driver.
     */
    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}

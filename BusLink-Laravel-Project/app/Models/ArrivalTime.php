<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArrivalTime extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'check_in_time',
        'check_in_date',
        'status', // on-time, late, absent
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'check_in_time' => 'datetime',
        'check_in_date' => 'date',
    ];

    /**
     * Get the driver that owns the arrival time.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}

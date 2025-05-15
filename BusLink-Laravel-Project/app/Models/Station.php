<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'description',
        'status' // active, inactive
    ];

    /**
     * Get the routes that include this station.
     */
    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_stations')
                    ->withPivot('order', 'arrival_time', 'departure_time')
                    ->withTimestamps();
    }
}

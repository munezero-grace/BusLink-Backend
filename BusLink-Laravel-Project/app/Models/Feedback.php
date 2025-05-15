<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'driver_id',
        'booking_id',
        'rating',
        'comment',
        'type', // feedback, claim, report
        'status', // pending, reviewed, resolved
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Get the user that gave the feedback.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the driver that received the feedback.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the booking associated with the feedback.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}

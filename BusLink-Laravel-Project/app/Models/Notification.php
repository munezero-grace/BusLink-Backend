<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type', // info, warning, success, error, schedule, booking, feedback, etc.
        'read_at',
        'data', // JSON data for additional info
        'link', // Optional link to redirect user
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark notification as read.
     *
     * @return bool
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->read_at = now();
            return $this->save();
        }
        
        return false;
    }

    /**
     * Mark notification as unread.
     *
     * @return bool
     */
    public function markAsUnread()
    {
        if (!is_null($this->read_at)) {
            $this->read_at = null;
            return $this->save();
        }
        
        return false;
    }

    /**
     * Check if notification is read.
     *
     * @return bool
     */
    public function isRead()
    {
        return !is_null($this->read_at);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = $request->user()->notifications();
        
        // Filter by read/unread status
        if ($request->has('read')) {
            if ($request->read === 'true' || $request->read === '1') {
                $query->read();
            } else {
                $query->unread();
            }
        }
        
        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        // Sort by newest first (default)
        $query->latest();
        
        // Paginate results
        $limit = $request->limit ?? 10;
        $notifications = $query->paginate($limit);
        
        return response()->json($notifications);
    }

    /**
     * Store a newly created notification in storage.
     * (For admin to send notifications to users)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|required|exists:users,id',
            'role' => 'sometimes|required|string|in:admin,driver,passenger',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|string|max:50',
            'data' => 'nullable|array',
            'link' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $created = 0;
        $failed = 0;

        // Send to specific user
        if ($request->has('user_id')) {
            $notification = Notification::create([
                'user_id' => $request->user_id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'data' => $request->data,
                'link' => $request->link,
            ]);
            
            if ($notification) {
                $created++;
            } else {
                $failed++;
            }
        }
        // Send to all users with specific role
        elseif ($request->has('role')) {
            $users = User::where('role', $request->role)->where('status', 'active')->get();
            
            foreach ($users as $user) {
                $notification = Notification::create([
                    'user_id' => $user->id,
                    'title' => $request->title,
                    'message' => $request->message,
                    'type' => $request->type,
                    'data' => $request->data,
                    'link' => $request->link,
                ]);
                
                if ($notification) {
                    $created++;
                } else {
                    $failed++;
                }
            }
        } else {
            return response()->json(['message' => 'Either user_id or role must be provided'], 422);
        }
        
        return response()->json([
            'message' => "Notification(s) created successfully. Created: {$created}, Failed: {$failed}",
            'created' => $created,
            'failed' => $failed,
        ], 201);
    }

    /**
     * Mark notification as read.
     *
     * @param  \App\Models\Notification  $notification
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAsRead(Notification $notification, Request $request)
    {
        // Check if notification belongs to auth user
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();
        
        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    }

    /**
     * Mark all notifications as read for authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAllAsRead(Request $request)
    {
        $count = $request->user()->notifications()->unread()->update(['read_at' => now()]);
        
        return response()->json([
            'message' => "{$count} notifications marked as read",
            'count' => $count
        ]);
    }
    
    /**
     * Delete a notification.
     *
     * @param  \App\Models\Notification  $notification
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Notification $notification, Request $request)
    {
        // Check if notification belongs to auth user
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();
        
        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }
    
    /**
     * Delete all notifications for authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroyAll(Request $request)
    {
        $count = $request->user()->notifications()->count();
        $request->user()->notifications()->delete();
        
        return response()->json([
            'message' => "{$count} notifications deleted successfully",
            'count' => $count
        ]);
    }
    
    /**
     * Count unread notifications for authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function unreadCount(Request $request)
    {
        $count = $request->user()->notifications()->unread()->count();
        
        return response()->json([
            'unread_count' => $count
        ]);
    }
}

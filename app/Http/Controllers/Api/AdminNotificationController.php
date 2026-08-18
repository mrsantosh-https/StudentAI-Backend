<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminNotificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | List Notifications
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $query = Notification::with([
            'user:id,name,email',
        ]);

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Read/unread filter
        if ($request->filled('is_read')) {
            $isRead = filter_var(
                $request->is_read,
                FILTER_VALIDATE_BOOLEAN
            );

            $query->where('is_read', $isRead);
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        $notifications = $query
            ->latest()
            ->paginate(15);

        $stats = [
            'total' => Notification::count(),

            'read' => Notification::where(
                'is_read',
                true
            )->count(),

            'unread' => Notification::where(
                'is_read',
                false
            )->count(),

            'users_with_notifications' =>
                Notification::distinct('user_id')
                    ->count('user_id'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'notifications' => $notifications,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Notification
    |--------------------------------------------------------------------------
    */

    public function show(
        Notification $notification
    ): JsonResponse {
        $notification->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'notification' => $notification,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Send Notification To One User
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
            ],

            'type' => [
                'required',
                Rule::in([
                    'success',
                    'info',
                    'warning',
                    'error',
                ]),
            ],
        ]);

        $notification = Notification::create([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'],
            'is_read' => false,
        ]);

        $notification->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully.',
            'notification' => $notification,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Broadcast Notification To All Users
    |--------------------------------------------------------------------------
    */

    public function broadcast(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
            ],

            'type' => [
                'required',
                Rule::in([
                    'success',
                    'info',
                    'warning',
                    'error',
                ]),
            ],
        ]);

        $users = User::select('id')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No users found.',
            ], 404);
        }

        $now = now();

        $notifications = $users->map(
            function ($user) use ($validated, $now) {
                return [
                    'user_id' => $user->id,
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'type' => $validated['type'],
                    'is_read' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        )->toArray();

        Notification::insert($notifications);

        return response()->json([
            'success' => true,
            'message' =>
                'Notification sent to all users successfully.',
            'users_notified' => count($notifications),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark As Read
    |--------------------------------------------------------------------------
    */

    public function markAsRead(
        Notification $notification
    ): JsonResponse {
        $notification->update([
            'is_read' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'notification' => $notification->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Notification
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Notification $notification
    ): JsonResponse {
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }
}
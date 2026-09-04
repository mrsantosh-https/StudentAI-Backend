<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NotificationController extends Controller
{
    /**
     * Get logged-in user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $unreadCount = $notifications
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $updated = Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'updated_count' => $updated,
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    /**
     * Create notification
     *
     * This method respects user's notification preferences.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

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
                'nullable',
                'string',
                'in:info,success,warning,danger',
            ],

            'category' => [
                'nullable',
                'string',
                'in:support,ai,job,marketing,system',
            ],
        ]);

        try {
            $preferences = NotificationPreference::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'in_app_notifications' => true,
                    'email_notifications' => true,
                    'support_notifications' => true,
                    'ai_notifications' => true,
                    'job_notifications' => true,
                    'marketing_notifications' => false,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Master In-App Notification Switch
            |--------------------------------------------------------------------------
            */
            if (!$preferences->in_app_notifications) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => 'In-app notifications are disabled.',
                    'notification' => null,
                ]);
            }

            $category = $validated['category'] ?? 'system';

            /*
            |--------------------------------------------------------------------------
            | Category Preferences
            |--------------------------------------------------------------------------
            */

            if (
                $category === 'support' &&
                !$preferences->support_notifications
            ) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Support notifications are disabled.',
                    'notification' => null,
                ]);
            }

            if (
                $category === 'ai' &&
                !$preferences->ai_notifications
            ) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => 'AI notifications are disabled.',
                    'notification' => null,
                ]);
            }

            if (
                $category === 'job' &&
                !$preferences->job_notifications
            ) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Job notifications are disabled.',
                    'notification' => null,
                ]);
            }

            if (
                $category === 'marketing' &&
                !$preferences->marketing_notifications
            ) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Marketing notifications are disabled.',
                    'notification' => null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Create Notification
            |--------------------------------------------------------------------------
            */

            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'] ?? 'info',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'skipped' => false,
                'message' => 'Notification created successfully.',
                'notification' => $notification,
            ], 201);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to create notification.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }
}
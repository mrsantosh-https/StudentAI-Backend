<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NotificationPreferenceController extends Controller
{
    /**
     * Default notification settings
     */
    private function defaultPreferences(): array
    {
        return [
            'in_app_notifications' => true,
            'email_notifications' => true,
            'support_notifications' => true,
            'ai_notifications' => true,
            'job_notifications' => true,
            'marketing_notifications' => false,
        ];
    }

    /**
     * Get notification preferences
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

        $preferences = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            $this->defaultPreferences()
        );

        return response()->json([
            'success' => true,
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update notification preferences
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'in_app_notifications' => ['required', 'boolean'],
            'email_notifications' => ['required', 'boolean'],
            'support_notifications' => ['required', 'boolean'],
            'ai_notifications' => ['required', 'boolean'],
            'job_notifications' => ['required', 'boolean'],
            'marketing_notifications' => ['required', 'boolean'],
        ]);

        try {
            $preferences = NotificationPreference::updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully.',
                'preferences' => $preferences,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to update notification settings.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /**
     * Reset notification preferences
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $preferences = NotificationPreference::updateOrCreate(
                ['user_id' => $user->id],
                $this->defaultPreferences()
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification settings reset successfully.',
                'preferences' => $preferences,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reset notification settings.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }
}
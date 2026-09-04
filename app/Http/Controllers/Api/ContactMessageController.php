<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactMessageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Store Contact Message
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Get Authenticated User
            |--------------------------------------------------------------------------
            */

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login again.',
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Request
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:150',
                ],

                'subject' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'message' => [
                    'required',
                    'string',
                    'min:5',
                    'max:5000',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Contact Message
            |--------------------------------------------------------------------------
            */

            $contactMessage = ContactMessage::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'status' => 'new',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully!',
                'data' => $contactMessage->load('user'),
            ], 201);

        } catch (ValidationException $error) {

            return response()->json([
                'success' => false,
                'message' => 'Please check your form fields.',
                'errors' => $error->errors(),
            ], 422);

        } catch (\Throwable $error) {

            Log::error('Contact message store error', [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Message could not be sent. Please try again.',
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Admin - Get All Contact Messages
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */

            $perPage = (int) $request->get('per_page', 20);

            $perPage = min(
                max($perPage, 1),
                100
            );

            $messages = ContactMessage::with('user')
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Contact messages fetched successfully.',
                'data' => $messages,
            ]);

        } catch (\Throwable $error) {

            Log::error('Contact messages fetch error', [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch messages.',
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Admin - Get Single Contact Message
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        try {

            $message = ContactMessage::with('user')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Contact message fetched successfully.',
                'data' => $message,
            ]);

        } catch (ModelNotFoundException $error) {

            return response()->json([
                'success' => false,
                'message' => 'Message not found.',
            ], 404);

        } catch (\Throwable $error) {

            Log::error('Contact message fetch error', [
                'contact_message_id' => $id,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch message.',
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Admin - Update Message Status
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, $id)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Validate Status
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([
                'status' => [
                    'required',
                    'in:new,read,resolved',
                ],
            ]);


            /*
            |--------------------------------------------------------------------------
            | Find Contact Message
            |--------------------------------------------------------------------------
            */

            $message = ContactMessage::findOrFail($id);


            /*
            |--------------------------------------------------------------------------
            | Previous Status
            |--------------------------------------------------------------------------
            */

            $previousStatus = $message->status;


            /*
            |--------------------------------------------------------------------------
            | Update Status
            |--------------------------------------------------------------------------
            */

            $message->update([
                'status' => $validated['status'],
            ]);


            /*
            |--------------------------------------------------------------------------
            | Notification Variables
            |--------------------------------------------------------------------------
            */

            $notificationCreated = false;
            $notificationSkipped = false;
            $notificationSkipReason = null;


            /*
            |--------------------------------------------------------------------------
            | Notify User When Message Is Resolved
            |--------------------------------------------------------------------------
            |
            | Notification will be created only when:
            |
            | 1. New status is resolved
            | 2. Previous status was not resolved
            | 3. User ID exists
            | 4. In-app notifications are enabled
            | 5. Support notifications are enabled
            |
            */

            if (
                $validated['status'] === 'resolved' &&
                $previousStatus !== 'resolved'
            ) {

                /*
                |--------------------------------------------------------------------------
                | Check User
                |--------------------------------------------------------------------------
                */

                if (!empty($message->user_id)) {

                    /*
                    |--------------------------------------------------------------------------
                    | Get / Create Notification Preferences
                    |--------------------------------------------------------------------------
                    */

                    $preferences = NotificationPreference::firstOrCreate(
                        [
                            'user_id' => $message->user_id,
                        ],
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
                    | Check Master In-App Notification Setting
                    |--------------------------------------------------------------------------
                    */

                    if (!$preferences->in_app_notifications) {

                        $notificationSkipped = true;

                        $notificationSkipReason =
                            'In-app notifications are disabled by the user.';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Check Support Notification Setting
                    |--------------------------------------------------------------------------
                    */

                    elseif (!$preferences->support_notifications) {

                        $notificationSkipped = true;

                        $notificationSkipReason =
                            'Support notifications are disabled by the user.';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Create Notification
                    |--------------------------------------------------------------------------
                    */

                    else {

                        Notification::create([
                            'user_id' => $message->user_id,

                            'title' =>
                                'Support Request Resolved 🎉',

                            'message' =>
                                'Your message regarding "' .
                                $message->subject .
                                '" has been resolved by the StudentAI support team.',

                            'type' => 'success',

                            'is_read' => false,
                        ]);

                        $notificationCreated = true;
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Missing User ID
                    |--------------------------------------------------------------------------
                    */

                    $notificationSkipped = true;

                    $notificationSkipReason =
                        'User ID is missing.';

                    Log::warning(
                        'Contact message resolved but user_id is missing.',
                        [
                            'contact_message_id' => $message->id,
                            'subject' => $message->subject,
                        ]
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Response Message
            |--------------------------------------------------------------------------
            */

            if ($validated['status'] === 'resolved') {

                if ($notificationCreated) {

                    $responseMessage =
                        'Message resolved successfully and user notified.';

                } elseif ($notificationSkipped) {

                    $responseMessage =
                        'Message resolved successfully. User notification was skipped because: ' .
                        $notificationSkipReason;

                } else {

                    $responseMessage =
                        'Message resolved successfully.';
                }

            } else {

                $responseMessage =
                    'Message status updated successfully.';
            }


            /*
            |--------------------------------------------------------------------------
            | Return Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' => $responseMessage,

                'notification_sent' =>
                    $notificationCreated,

                'notification_skipped' =>
                    $notificationSkipped,

                'notification_skip_reason' =>
                    $notificationSkipReason,

                'data' =>
                    $message->fresh()->load('user'),
            ]);

        } catch (ValidationException $error) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid status.',
                'errors' => $error->errors(),
            ], 422);

        } catch (ModelNotFoundException $error) {

            return response()->json([
                'success' => false,
                'message' => 'Contact message not found.',
            ], 404);

        } catch (\Throwable $error) {

            Log::error(
                'Contact message status update error',
                [
                    'contact_message_id' => $id,

                    'message' =>
                        $error->getMessage(),

                    'file' =>
                        $error->getFile(),

                    'line' =>
                        $error->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Unable to update message status.',
                'error' => config('app.debug')
                    ? $error->getMessage()
                    : null,
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Admin - Delete Contact Message
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        try {

            $message = ContactMessage::findOrFail($id);

            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully.',
            ]);

        } catch (ModelNotFoundException $error) {

            return response()->json([
                'success' => false,
                'message' => 'Message not found.',
            ], 404);

        } catch (\Throwable $error) {

            Log::error(
                'Contact message delete error',
                [
                    'contact_message_id' => $id,

                    'message' =>
                        $error->getMessage(),

                    'file' =>
                        $error->getFile(),

                    'line' =>
                        $error->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete message.',
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;
use App\Models\LoginActivity;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\DeletedAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Notification;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Welcome to StudentAI',
            'message' =>
                'Your account has been created successfully. Welcome to StudentAI!',
            'type' => 'success',
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        /*
        |--------------------------------------------------------------------------
        | Invalid Credentials
        |--------------------------------------------------------------------------
        */

        if (
            !$user ||
            !Hash::check($request->password, $user->password)
        ) {
            LoginActivity::create([
                'user_id' => $user?->id,
                'login_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => 'Invalid credentials',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Blocked User
        |--------------------------------------------------------------------------
        */

        if ((bool) $user->is_blocked === true) {

            // Revoke any existing tokens
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            LoginActivity::create([
                'user_id' => $user->id,
                'login_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'blocked',
                'failure_reason' => 'Account is blocked',
            ]);

            return response()->json([
                'success' => false,
                'blocked' => true,
                'message' =>
                    'Your account has been blocked by the administrator.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Successful Login
        |--------------------------------------------------------------------------
        */

        $loginActivity = LoginActivity::create([
            'user_id' => $user->id,
            'login_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
            'failure_reason' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Sanctum Token
        |--------------------------------------------------------------------------
        */

        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'login_activity_id' => $loginActivity->id,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    */

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'linkedin' => 'nullable|string|max:255',
            'github' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
        ]);

        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'linkedin' => $request->linkedin,
            'github' => $request->github,
            'bio' => $request->bio,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Profile Photo
    |--------------------------------------------------------------------------
    */

    public function uploadProfilePhoto(Request $request)
    {
        $request->validate([
            'profile_photo' =>
                'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();

        $path = $request
            ->file('profile_photo')
            ->store(
                'profile_photos',
                'public'
            );

        $user->update([
            'profile_photo' => $path,
        ]);

        return response()->json([
            'message' =>
                'Profile photo uploaded successfully',

            'profile_photo' =>
                asset('storage/' . $path),

            'user' => $user,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Account
    |--------------------------------------------------------------------------
    */

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {

            DB::transaction(
                function () use ($user, $request) {

                    /*
                    |--------------------------------------------------------------------------
                    | Store deleted account
                    |--------------------------------------------------------------------------
                    */

                    DeletedAccount::create([
                        'original_user_id' =>
                            $user->id,

                        'name' =>
                            $user->name,

                        'email' =>
                            $user->email,

                        'phone' =>
                            $user->phone ?? null,

                        'profile_photo' =>
                            $user->profile_photo ?? null,

                        'deleted_by' =>
                            'user',

                        'reason' =>
                            $request->input('reason'),

                        'metadata' => [
                            'deleted_from' =>
                                'StudentAI',

                            'ip_address' =>
                                $request->ip(),
                        ],

                        'account_deleted_at' =>
                            now(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Delete Sanctum tokens
                    |--------------------------------------------------------------------------
                    */

                    if (
                        method_exists(
                            $user,
                            'tokens'
                        )
                    ) {
                        $user
                            ->tokens()
                            ->delete();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Permanently delete user
                    |--------------------------------------------------------------------------
                    */

                    $user->delete();
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Account permanently deleted successfully.',
            ], 200);

        } catch (\Throwable $error) {

            Log::error(
                'Delete account error',
                [
                    'user_id' =>
                        $user->id ?? null,

                    'message' =>
                        $error->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Account could not be deleted.',
            ], 500);
        }
    }
}
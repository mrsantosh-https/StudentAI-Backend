<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Get Profile
    |--------------------------------------------------------------------------
    */

    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    */

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'linkedin' => [
                'nullable',
                'string',
                'max:255',
            ],

            'github' => [
                'nullable',
                'string',
                'max:255',
            ],

            'bio' => [
                'nullable',
                'string',
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Upload Profile Photo
    |--------------------------------------------------------------------------
    */

    public function uploadProfilePhoto(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Check File Exists
        |--------------------------------------------------------------------------
        */

        if (!$request->hasFile('profile_photo')) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a profile photo.',
                'errors' => [
                    'profile_photo' => [
                        'Profile photo is required.'
                    ],
                ],
            ], 422);
        }

        $file = $request->file('profile_photo');

        /*
        |--------------------------------------------------------------------------
        | Check Upload Error
        |--------------------------------------------------------------------------
        */

        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'The uploaded file is invalid.',
                'errors' => [
                    'profile_photo' => [
                        'Invalid image upload.'
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check File Size
        |--------------------------------------------------------------------------
        |
        | Maximum 2 MB
        | No minimum size
        |
        */

        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'Image size must not exceed 2 MB.',
                'errors' => [
                    'profile_photo' => [
                        'Maximum image size is 2 MB.'
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Extension
        |--------------------------------------------------------------------------
        */

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
        ];

        if (!in_array($extension, $allowedExtensions, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only JPG, JPEG and PNG images are allowed.',
                'errors' => [
                    'profile_photo' => [
                        'Only JPG, JPEG and PNG images are allowed.'
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Actual Image
        |--------------------------------------------------------------------------
        */

        $imageInfo = @getimagesize(
            $file->getRealPath()
        );

        if ($imageInfo === false) {
            return response()->json([
                'success' => false,
                'message' => 'The selected file is not a valid image.',
                'errors' => [
                    'profile_photo' => [
                        'Please select a valid JPG, JPEG or PNG image.'
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Old Profile Photo
        |--------------------------------------------------------------------------
        */

        $user = $request->user();

        if (!empty($user->profile_photo)) {

            if (
                Storage::disk('public')->exists(
                    $user->profile_photo
                )
            ) {
                Storage::disk('public')->delete(
                    $user->profile_photo
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Store New Photo
        |--------------------------------------------------------------------------
        */

        $path = $file->store(
            'profile_photos',
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | Save Database
        |--------------------------------------------------------------------------
        */

        $user->update([
            'profile_photo' => $path,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Profile photo uploaded successfully.',
            'profile_photo' => $path,
            'user' => $user->fresh(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Profile Photo
    |--------------------------------------------------------------------------
    */

    public function removeProfilePhoto(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | No Photo
        |--------------------------------------------------------------------------
        */

        if (empty($user->profile_photo)) {
            return response()->json([
                'success' => true,
                'message' => 'No profile photo to remove.',
                'profile_photo' => null,
                'user' => $user,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Physical File
        |--------------------------------------------------------------------------
        */

        if (
            Storage::disk('public')->exists(
                $user->profile_photo
            )
        ) {
            Storage::disk('public')->delete(
                $user->profile_photo
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Database Path
        |--------------------------------------------------------------------------
        */

        $user->update([
            'profile_photo' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Profile photo removed successfully.',
            'profile_photo' => null,
            'user' => $user->fresh(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => [
                'required',
            ],

            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Check Current Password
        |--------------------------------------------------------------------------
        */

        if (
            !Hash::check(
                $request->current_password,
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Current password is incorrect.',

                'errors' => [
                    'current_password' => [
                        'Current password is incorrect.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Password
        |--------------------------------------------------------------------------
        */

        $user->update([
            'password' => Hash::make(
                $request->new_password
            ),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
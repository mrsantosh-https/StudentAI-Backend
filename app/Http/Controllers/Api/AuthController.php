<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
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

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }

public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Invalid login details'
        ], 401);
    }

   $token = $user->createToken('studentai-token')->plainTextToken;

   $token = $user->createToken('studentai-token')->plainTextToken;

return response()->json([
    'message' => 'Login successful',
    'token' => $token,
    'user' => $user
]);
}

public function profile(Request $request)
{
    return response()->json($request->user());
}

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
public function uploadProfilePhoto(Request $request)
{
    $request->validate([
        'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $user = $request->user();

    $path = $request->file('profile_photo')->store('profile_photos', 'public');

    $user->update([
        'profile_photo' => $path,
    ]);

    return response()->json([
        'message' => 'Profile photo uploaded successfully',
        'profile_photo' => asset('storage/' . $path),
        'user' => $user,
    ]);
}
}
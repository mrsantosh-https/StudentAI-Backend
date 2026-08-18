<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | List Users
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = User::query()->select([
            'id',
            'name',
            'email',
            'role',
            'is_blocked',
            'blocked_at',
            'phone',
            'profile_photo',
            'created_at',
            'updated_at',
        ]);

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if (
            $request->filled('role') &&
            in_array($request->role, ['user', 'admin'], true)
        ) {
            $query->where('role', $request->role);
        }

        // Status filter
        if ($request->status === 'blocked') {
            $query->where('is_blocked', true);
        }

        if ($request->status === 'active') {
            $query->where('is_blocked', false);
        }

        $users = $query
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show User
    |--------------------------------------------------------------------------
    */

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Block User
    |--------------------------------------------------------------------------
    */

    public function block(Request $request, User $user)
    {
        // Admin cannot block himself
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot block your own account.',
            ], 422);
        }

        // Already blocked
        if ((bool) $user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'User is already blocked.',
                'user' => $user->fresh(),
            ], 422);
        }

        DB::transaction(function () use ($user) {

            /*
            |--------------------------------------------------------------------------
            | Mark user as blocked
            |--------------------------------------------------------------------------
            */

            $user->update([
                'is_blocked' => true,
                'blocked_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Revoke ALL existing Sanctum tokens
            |--------------------------------------------------------------------------
            |
            | This immediately logs the blocked user out from API authentication.
            |
            */

            $user->tokens()->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User blocked successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Unblock User
    |--------------------------------------------------------------------------
    */

    public function unblock(User $user)
    {
        // Already active
        if (!(bool) $user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'User is already active.',
                'user' => $user->fresh(),
            ], 422);
        }

        $user->update([
            'is_blocked' => false,
            'blocked_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User unblocked successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Change Role
    |--------------------------------------------------------------------------
    */

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => [
                'required',
                Rule::in(['user', 'admin']),
            ],
        ]);

        // Admin cannot remove his own admin role
        if (
            $request->user()->id === $user->id &&
            $validated['role'] !== 'admin'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove your own admin role.',
            ], 422);
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete User
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, User $user)
    {
        // Admin cannot delete himself
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own admin account.',
            ], 422);
        }

        DB::transaction(function () use ($user) {

            // Revoke all API tokens
            $user->tokens()->delete();

            // Delete user
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
}
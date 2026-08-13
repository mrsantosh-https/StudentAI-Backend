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
        $query = User::query()
            ->select([
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

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('role') &&
            in_array($request->role, ['user', 'admin'])
        ) {
            $query->where('role', $request->role);
        }

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
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot block your own account.',
            ], 422);
        }

        $user->update([
            'is_blocked' => true,
            'blocked_at' => now(),
        ]);

        /*
         * Existing Sanctum tokens remove.
         * Blocked user gets logged out from authenticated API.
         */
        $user->tokens()->delete();

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
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own admin account.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
}
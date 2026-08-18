<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use Illuminate\Http\Request;

class AdminLoginActivityController extends Controller
{
    /**
     * List login activities
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:success,failed,blocked'],
            'date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = LoginActivity::query()
            ->with([
                'user:id,name,email',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);

            $query->whereHas('user', function ($q) use ($search) {
                $q->where(function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Date Filter
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['date'])) {
            $query->whereDate(
                'login_at',
                $validated['date']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = $validated['per_page'] ?? 15;

        $activities = $query
            ->orderByDesc('login_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Login activities fetched successfully.',
            'activities' => $activities,
        ]);
    }

    /**
     * Show single login activity
     */
    public function show(LoginActivity $loginActivity)
    {
        $loginActivity->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login activity fetched successfully.',
            'activity' => $loginActivity,
        ]);
    }
}
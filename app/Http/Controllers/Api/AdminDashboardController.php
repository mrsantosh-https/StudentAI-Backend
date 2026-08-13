<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Resume;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function stats(Request $request)
    {
        return response()->json([
            'success' => true,

            'stats' => [
                'total_users' => User::count(),
                'total_resumes' => Resume::count(),

                'admin_users' => User::where(
                    'role',
                    'admin'
                )->count(),

                'normal_users' => User::where(
                    'role',
                    'user'
                )->count(),
            ],
        ]);
    }
}
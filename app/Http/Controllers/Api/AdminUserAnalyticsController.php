<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;

class AdminUserAnalyticsController extends Controller
{
    public function index()
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Basic User Stats
            |--------------------------------------------------------------------------
            */

            $totalUsers = User::count();

            $normalUsers = User::where(
                'role',
                'user'
            )->count();

            $adminUsers = User::where(
                'role',
                'admin'
            )->count();

            /*
            |--------------------------------------------------------------------------
            | New Users
            |--------------------------------------------------------------------------
            */

            $todayUsers = User::whereDate(
                'created_at',
                Carbon::today()
            )->count();

            $thisWeekUsers = User::where(
                'created_at',
                '>=',
                Carbon::now()->startOfWeek()
            )->count();

            $thisMonthUsers = User::where(
                'created_at',
                '>=',
                Carbon::now()->startOfMonth()
            )->count();

            /*
            |--------------------------------------------------------------------------
            | Last 7 Days Registration Analytics
            |--------------------------------------------------------------------------
            */

            $registrationChart = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);

                $count = User::whereDate(
                    'created_at',
                    $date
                )->count();

                $registrationChart[] = [
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->format('D'),
                    'users' => $count,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Recent Users
            |--------------------------------------------------------------------------
            */

            $recentUsers = User::select(
                'id',
                'name',
                'email',
                'role',
                'created_at'
            )
                ->latest()
                ->limit(10)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'stats' => [
                    'total_users' => $totalUsers,
                    'normal_users' => $normalUsers,
                    'admin_users' => $adminUsers,
                    'today_users' => $todayUsers,
                    'week_users' => $thisWeekUsers,
                    'month_users' => $thisMonthUsers,
                ],

                'registration_chart' =>
                    $registrationChart,

                'recent_users' =>
                    $recentUsers,
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User analytics could not be loaded.',
                'error' =>
                    $error->getMessage(),
            ], 500);
        }
    }
}
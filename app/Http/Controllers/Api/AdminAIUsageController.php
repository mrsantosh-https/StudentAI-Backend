<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIUsage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAIUsageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Available StudentAI Tools
    |--------------------------------------------------------------------------
    */

    private array $availableTools = [
        'ai_career_coach',
        'resume_builder',
        'resume_analysis',
        'resume_improvement',
        'cover_letter',
        'job_matcher',
        'ai_interview',
        'interview_feedback',
        'mock_interview',
        'career_roadmap',
    ];

    /*
    |--------------------------------------------------------------------------
    | AI Usage Analytics
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Basic Statistics
        |--------------------------------------------------------------------------
        */

        $totalRequests = AIUsage::count();

        $successfulRequests = AIUsage::where(
            'status',
            'success'
        )->count();

        $failedRequests = AIUsage::where(
            'status',
            'failed'
        )->count();

        $totalTokens = AIUsage::sum(
            'total_tokens'
        );

        /*
        |--------------------------------------------------------------------------
        | Today's Usage
        |--------------------------------------------------------------------------
        */

        $todayRequests = AIUsage::whereDate(
            'created_at',
            today()
        )->count();

        $todayTokens = AIUsage::whereDate(
            'created_at',
            today()
        )->sum(
            'total_tokens'
        );

        /*
        |--------------------------------------------------------------------------
        | This Week Usage
        |--------------------------------------------------------------------------
        */

        $weekStart =
            now()->copy()->startOfWeek();

        $weekEnd =
            now()->copy()->endOfWeek();

        $weekRequests = AIUsage::whereBetween(
            'created_at',
            [
                $weekStart,
                $weekEnd,
            ]
        )->count();

        $weekTokens = AIUsage::whereBetween(
            'created_at',
            [
                $weekStart,
                $weekEnd,
            ]
        )->sum(
            'total_tokens'
        );

        /*
        |--------------------------------------------------------------------------
        | Active AI Users
        |--------------------------------------------------------------------------
        */

        $activeUsers = AIUsage::whereNotNull(
            'user_id'
        )
            ->distinct()
            ->count(
                'user_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Success Rate
        |--------------------------------------------------------------------------
        */

        $successRate =
            $totalRequests > 0
                ? round(
                    (
                        $successfulRequests /
                        $totalRequests
                    ) * 100,
                    2
                )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Database Tool Usage
        |--------------------------------------------------------------------------
        */

        $databaseToolUsage =
            AIUsage::select(
                'tool',

                DB::raw(
                    'COUNT(*) as total_requests'
                ),

                DB::raw(
                    'SUM(total_tokens) as total_tokens'
                ),

                DB::raw(
                    "SUM(
                        CASE
                            WHEN status = 'success'
                            THEN 1
                            ELSE 0
                        END
                    ) as successful_requests"
                ),

                DB::raw(
                    "SUM(
                        CASE
                            WHEN status = 'failed'
                            THEN 1
                            ELSE 0
                        END
                    ) as failed_requests"
                )
            )
                ->groupBy(
                    'tool'
                )
                ->get()
                ->keyBy(
                    'tool'
                );

        /*
        |--------------------------------------------------------------------------
        | All Tool Usage
        |--------------------------------------------------------------------------
        */

        $toolUsage = collect(
            $this->availableTools
        )
            ->map(
                function ($tool)
                use ($databaseToolUsage) {

                    $usage =
                        $databaseToolUsage->get(
                            $tool
                        );

                    return [
                        'tool' =>
                            $tool,

                        'total_requests' =>
                            $usage
                                ? (int)
                                    $usage->total_requests
                                : 0,

                        'successful_requests' =>
                            $usage
                                ? (int)
                                    $usage->successful_requests
                                : 0,

                        'failed_requests' =>
                            $usage
                                ? (int)
                                    $usage->failed_requests
                                : 0,

                        'total_tokens' =>
                            $usage
                                ? (int)
                                    $usage->total_tokens
                                : 0,
                    ];
                }
            )
            ->sortByDesc(
                'total_requests'
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Include Future / Unknown Tools
        |--------------------------------------------------------------------------
        */

        $extraTools =
            $databaseToolUsage
                ->filter(
                    function ($usage, $tool) {
                        return !in_array(
                            $tool,
                            $this->availableTools,
                            true
                        );
                    }
                )
                ->map(
                    function ($usage) {
                        return [
                            'tool' =>
                                $usage->tool,

                            'total_requests' =>
                                (int)
                                $usage->total_requests,

                            'successful_requests' =>
                                (int)
                                $usage->successful_requests,

                            'failed_requests' =>
                                (int)
                                $usage->failed_requests,

                            'total_tokens' =>
                                (int)
                                $usage->total_tokens,
                        ];
                    }
                )
                ->values();

        $toolUsage =
            $toolUsage
                ->concat(
                    $extraTools
                )
                ->sortByDesc(
                    'total_requests'
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Daily Usage - Last 7 Days
        |--------------------------------------------------------------------------
        */

        $databaseDailyUsage =
            AIUsage::select(
                DB::raw(
                    'DATE(created_at) as date'
                ),

                DB::raw(
                    'COUNT(*) as total_requests'
                ),

                DB::raw(
                    'SUM(total_tokens) as total_tokens'
                )
            )
                ->where(
                    'created_at',
                    '>=',
                    now()
                        ->subDays(6)
                        ->startOfDay()
                )
                ->groupBy(
                    DB::raw(
                        'DATE(created_at)'
                    )
                )
                ->orderBy(
                    'date'
                )
                ->get()
                ->keyBy(
                    'date'
                );

        $dailyUsage = collect(
            range(6, 0)
        )->map(
            function ($daysAgo)
            use ($databaseDailyUsage) {

                $date =
                    now()
                        ->subDays(
                            $daysAgo
                        )
                        ->format(
                            'Y-m-d'
                        );

                $usage =
                    $databaseDailyUsage
                        ->get(
                            $date
                        );

                return [
                    'date' =>
                        $date,

                    'total_requests' =>
                        $usage
                            ? (int)
                                $usage->total_requests
                            : 0,

                    'total_tokens' =>
                        $usage
                            ? (int)
                                $usage->total_tokens
                            : 0,
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Top AI Users
        |--------------------------------------------------------------------------
        */

        $topUsers =
            User::query()
                ->join(
                    'ai_usages',
                    'users.id',
                    '=',
                    'ai_usages.user_id'
                )
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',

                    DB::raw(
                        'COUNT(ai_usages.id) as total_requests'
                    ),

                    DB::raw(
                        'SUM(ai_usages.total_tokens) as total_tokens'
                    )
                )
                ->groupBy(
                    'users.id',
                    'users.name',
                    'users.email'
                )
                ->orderByDesc(
                    'total_requests'
                )
                ->limit(
                    10
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Recent AI Activity
        |--------------------------------------------------------------------------
        */

        $recentActivity =
            AIUsage::with([
                'user:id,name,email',
            ])
                ->latest()
                ->limit(
                    20
                )
                ->get([
                    'id',
                    'user_id',
                    'tool',
                    'status',
                    'prompt_tokens',
                    'completion_tokens',
                    'total_tokens',
                    'error_message',
                    'created_at',
                ]);

        /*
        |--------------------------------------------------------------------------
        | API Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'stats' => [
                'total_requests' =>
                    $totalRequests,

                'successful_requests' =>
                    $successfulRequests,

                'failed_requests' =>
                    $failedRequests,

                'total_tokens' =>
                    (int)
                    $totalTokens,

                'active_users' =>
                    $activeUsers,

                'success_rate' =>
                    $successRate,

                'today_requests' =>
                    $todayRequests,

                'today_tokens' =>
                    (int)
                    $todayTokens,

                'week_requests' =>
                    $weekRequests,

                'week_tokens' =>
                    (int)
                    $weekTokens,
            ],

            'tool_usage' =>
                $toolUsage,

            'daily_usage' =>
                $dailyUsage,

            'top_users' =>
                $topUsers,

            'recent_activity' =>
                $recentActivity,
        ]);
    }
}
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
        | Base Activity Query
        |--------------------------------------------------------------------------
        */

        $activityQuery = AIUsage::with([
            'user:id,name,email',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim($request->search);

            $activityQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Tool Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('tool')) {
            $activityQuery->where(
                'tool',
                $request->tool
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status') &&
            in_array(
                $request->status,
                ['success', 'failed'],
                true
            )
        ) {
            $activityQuery->where(
                'status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        |
        | Summary respects the current filters.
        |
        */

        $summaryQuery = clone $activityQuery;

        $summary = $summaryQuery
            ->select([
                DB::raw('COUNT(*) as total_requests'),

                DB::raw("
                    SUM(
                        CASE
                            WHEN status = 'success'
                            THEN 1
                            ELSE 0
                        END
                    ) as successful_requests
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN status = 'failed'
                            THEN 1
                            ELSE 0
                        END
                    ) as failed_requests
                "),

                DB::raw(
                    'COALESCE(SUM(prompt_tokens), 0) as prompt_tokens'
                ),

                DB::raw(
                    'COALESCE(SUM(completion_tokens), 0) as completion_tokens'
                ),

                DB::raw(
                    'COALESCE(SUM(total_tokens), 0) as total_tokens'
                ),
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Tool Usage
        |--------------------------------------------------------------------------
        */

        $databaseToolUsage = AIUsage::select(
            'tool',

            DB::raw(
                'COUNT(*) as total_requests'
            ),

            DB::raw(
                'COALESCE(SUM(prompt_tokens), 0) as prompt_tokens'
            ),

            DB::raw(
                'COALESCE(SUM(completion_tokens), 0) as completion_tokens'
            ),

            DB::raw(
                'COALESCE(SUM(total_tokens), 0) as total_tokens'
            ),

            DB::raw("
                SUM(
                    CASE
                        WHEN status = 'success'
                        THEN 1
                        ELSE 0
                    END
                ) as successful_requests
            "),

            DB::raw("
                SUM(
                    CASE
                        WHEN status = 'failed'
                        THEN 1
                        ELSE 0
                    END
                ) as failed_requests
            ")
        )
            ->groupBy('tool')
            ->get()
            ->keyBy('tool');

        /*
        |--------------------------------------------------------------------------
        | All Known Tools
        |--------------------------------------------------------------------------
        */

        $toolUsage = collect(
            $this->availableTools
        )
            ->map(function ($tool) use ($databaseToolUsage) {

                $usage = $databaseToolUsage->get(
                    $tool
                );

                return [
                    'tool' => $tool,

                    'requests' => $usage
                        ? (int) $usage->total_requests
                        : 0,

                    'total_requests' => $usage
                        ? (int) $usage->total_requests
                        : 0,

                    'successful_requests' => $usage
                        ? (int) $usage->successful_requests
                        : 0,

                    'failed_requests' => $usage
                        ? (int) $usage->failed_requests
                        : 0,

                    'prompt_tokens' => $usage
                        ? (int) $usage->prompt_tokens
                        : 0,

                    'completion_tokens' => $usage
                        ? (int) $usage->completion_tokens
                        : 0,

                    'total_tokens' => $usage
                        ? (int) $usage->total_tokens
                        : 0,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Unknown / Future Tools
        |--------------------------------------------------------------------------
        */

        $extraTools = $databaseToolUsage
            ->filter(function ($usage, $tool) {
                return !in_array(
                    $tool,
                    $this->availableTools,
                    true
                );
            })
            ->map(function ($usage) {

                return [
                    'tool' => $usage->tool,

                    'requests' =>
                        (int) $usage->total_requests,

                    'total_requests' =>
                        (int) $usage->total_requests,

                    'successful_requests' =>
                        (int) $usage->successful_requests,

                    'failed_requests' =>
                        (int) $usage->failed_requests,

                    'prompt_tokens' =>
                        (int) $usage->prompt_tokens,

                    'completion_tokens' =>
                        (int) $usage->completion_tokens,

                    'total_tokens' =>
                        (int) $usage->total_tokens,
                ];
            })
            ->values();

        $toolUsage = $toolUsage
            ->concat($extraTools)
            ->sortByDesc('total_requests')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Status Usage
        |--------------------------------------------------------------------------
        */

        $statusUsage = AIUsage::select(
            'status',

            DB::raw(
                'COUNT(*) as requests'
            ),

            DB::raw(
                'COALESCE(SUM(total_tokens), 0) as total_tokens'
            )
        )
            ->groupBy('status')
            ->orderByDesc('requests')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Daily Usage - Last 7 Days
        |--------------------------------------------------------------------------
        */

        $databaseDailyUsage = AIUsage::select(
            DB::raw(
                'DATE(created_at) as date'
            ),

            DB::raw(
                'COUNT(*) as total_requests'
            ),

            DB::raw(
                'COALESCE(SUM(total_tokens), 0) as total_tokens'
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
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dailyUsage = collect(
            range(6, 0)
        )
            ->map(function ($daysAgo) use ($databaseDailyUsage) {

                $date = now()
                    ->subDays($daysAgo)
                    ->format('Y-m-d');

                $usage = $databaseDailyUsage->get(
                    $date
                );

                return [
                    'date' => $date,

                    'requests' => $usage
                        ? (int) $usage->total_requests
                        : 0,

                    'total_requests' => $usage
                        ? (int) $usage->total_requests
                        : 0,

                    'total_tokens' => $usage
                        ? (int) $usage->total_tokens
                        : 0,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Top AI Users
        |--------------------------------------------------------------------------
        */

        $topUsers = User::query()
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
                    'COALESCE(SUM(ai_usages.total_tokens), 0) as total_tokens'
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
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Paginated Activities
        |--------------------------------------------------------------------------
        */

        $activities = $activityQuery
            ->latest('created_at')
            ->paginate(15);

        /*
        |--------------------------------------------------------------------------
        | Success Rate
        |--------------------------------------------------------------------------
        */

        $totalRequests =
            (int) ($summary->total_requests ?? 0);

        $successfulRequests =
            (int) ($summary->successful_requests ?? 0);

        $successRate = $totalRequests > 0
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
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            /*
            |--------------------------------------------------------------------------
            | Frontend Summary
            |--------------------------------------------------------------------------
            */

            'summary' => [
                'total_requests' =>
                    $totalRequests,

                'successful_requests' =>
                    $successfulRequests,

                'failed_requests' =>
                    (int) ($summary->failed_requests ?? 0),

                'prompt_tokens' =>
                    (int) ($summary->prompt_tokens ?? 0),

                'completion_tokens' =>
                    (int) ($summary->completion_tokens ?? 0),

                'total_tokens' =>
                    (int) ($summary->total_tokens ?? 0),

                'success_rate' =>
                    $successRate,
            ],

            /*
            |--------------------------------------------------------------------------
            | Additional Stats
            |--------------------------------------------------------------------------
            */

            'stats' => [
                'total_requests' =>
                    $totalRequests,

                'successful_requests' =>
                    $successfulRequests,

                'failed_requests' =>
                    (int) ($summary->failed_requests ?? 0),

                'total_tokens' =>
                    (int) ($summary->total_tokens ?? 0),

                'active_users' =>
                    AIUsage::whereNotNull('user_id')
                        ->distinct()
                        ->count('user_id'),

                'success_rate' =>
                    $successRate,

                'today_requests' =>
                    AIUsage::whereDate(
                        'created_at',
                        today()
                    )->count(),

                'today_tokens' =>
                    (int) AIUsage::whereDate(
                        'created_at',
                        today()
                    )->sum('total_tokens'),

                'week_requests' =>
                    AIUsage::whereBetween(
                        'created_at',
                        [
                            now()->copy()->startOfWeek(),
                            now()->copy()->endOfWeek(),
                        ]
                    )->count(),

                'week_tokens' =>
                    (int) AIUsage::whereBetween(
                        'created_at',
                        [
                            now()->copy()->startOfWeek(),
                            now()->copy()->endOfWeek(),
                        ]
                    )->sum('total_tokens'),
            ],

            'tool_usage' =>
                $toolUsage,

            'status_usage' =>
                $statusUsage,

            'daily_usage' =>
                $dailyUsage,

            'top_users' =>
                $topUsers,

            /*
            |--------------------------------------------------------------------------
            | Paginated Activities
            |--------------------------------------------------------------------------
            */

            'activities' =>
                $activities,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Single AI Usage Activity
    |--------------------------------------------------------------------------
    */

    public function show(AIUsage $aiUsage)
    {
        $aiUsage->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'success' => true,

            'activity' =>
                $aiUsage,
        ]);
    }
}
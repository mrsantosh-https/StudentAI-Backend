<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resume;
use App\Models\JobApplication;
use App\Models\InterviewHistory;

class DashboardController extends Controller
{
    /**
     * ============================================================
     * DASHBOARD ANALYTICS
     * ============================================================
     */
    public function analytics(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,

            'total_resumes' => Resume::where(
                'user_id',
                $user->id
            )->count(),

            'total_jobs' => JobApplication::where(
                'user_id',
                $user->id
            )->count(),

            'total_interviews' => InterviewHistory::where(
                'user_id',
                $user->id
            )->count(),

            'average_ats' => Resume::where(
                'user_id',
                $user->id
            )->avg('ats_score') ?? 0,
        ], 200);
    }


    /**
     * ============================================================
     * DASHBOARD STATS
     * ============================================================
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,

            'total_resumes' => Resume::where(
                'user_id',
                $user->id
            )->count(),

            'total_jobs' => JobApplication::where(
                'user_id',
                $user->id
            )->count(),

            'total_interviews' => InterviewHistory::where(
                'user_id',
                $user->id
            )->count(),

            'average_ats' => Resume::where(
                'user_id',
                $user->id
            )->avg('ats_score') ?? 0,
        ], 200);
    }
}
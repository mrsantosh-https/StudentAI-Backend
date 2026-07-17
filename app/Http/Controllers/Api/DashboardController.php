<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resume;
use App\Models\JobApplication;
use App\Models\InterviewHistory;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();

        return response()->json([
            "total_resumes" => Resume::where("user_id", $user->id)->count(),

            "total_jobs" => JobApplication::where("user_id", $user->id)->count(),

            "total_interviews" => InterviewHistory::where("user_id", $user->id)->count(),

            "average_ats" => Resume::where("user_id", $user->id)->avg("ats_score") ?? 0,
        ]);
    }
}
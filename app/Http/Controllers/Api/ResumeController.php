<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;

class ResumeController extends Controller
{
    public function store(Request $request)
    {
        $resume = Resume::create([
            'user_id' => $request->user()->id,
            'title' => $request->title ?? 'Untitled Resume',
            'full_name' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'linkedin' => $request->linkedin,
            'github' => $request->github,
            'portfolio' => $request->portfolio,
            'summary' => $request->summary,
            'education' => $request->education,
            'skills' => $request->skills,
            'projects' => $request->projects,
            'experience' => $request->experience,
        ]);

        return response()->json([
            'message' => 'Resume saved successfully',
            'resume' => $resume
        ], 201);
    }
    public function index(Request $request)
{
    return $request->user()
        ->resumes()
        ->latest()
        ->get();
}
public function destroy(Request $request, $id)
{
    $resume = Resume::where('user_id', $request->user()->id)
        ->findOrFail($id);

    $resume->delete();

    return response()->json([
        'message' => 'Resume deleted successfully'
    ]);
}
public function updateAtsScore(Request $request, $id)
{
    $request->validate([
        'ats_score' => 'required|integer|min:0|max:100',
    ]);

    $resume = Resume::where('user_id', $request->user()->id)
        ->findOrFail($id);

    $resume->update([
        'ats_score' => $request->ats_score,
    ]);

    return response()->json([
        'message' => 'ATS score saved successfully',
        'resume' => $resume,
    ]);
}
public function analytics(Request $request)
{
    $user = $request->user();

    return response()->json([
        'total_resumes' => $user->resumes()->count(),
        'latest_resume' => $user->resumes()->latest()->first(),
        'profile_completion' => $this->profileCompletion($user),
        'total_jobs' => $user->jobApplications()->count(),
        'interview_jobs' => $user->jobApplications()->where('status', 'Interview')->count(),
        'offer_jobs' => $user->jobApplications()->where('status', 'Offer')->count(),
        'average_ats_score' => round($user->resumes()->avg('ats_score') ?? 0),
    ]);
}

private function profileCompletion($user)
{
    $fields = [
        $user->name,
        $user->email,
        $user->phone,
        $user->linkedin,
        $user->github,
        $user->bio,
        $user->profile_photo,
    ];

    $filled = collect($fields)->filter()->count();

    return round(($filled / count($fields)) * 100);
}
public function update(Request $request, $id)
{
    $resume = Resume::where('user_id', $request->user()->id)
        ->findOrFail($id);

    $resume->update([
        'title' => $request->title,
        'full_name' => $request->fullName,
        'email' => $request->email,
        'phone' => $request->phone,
        'linkedin' => $request->linkedin,
        'github' => $request->github,
        'portfolio' => $request->portfolio,
        'summary' => $request->summary,
        'education' => $request->education,
        'skills' => $request->skills,
        'projects' => $request->projects,
        'experience' => $request->experience,
    ]);

    return response()->json([
        'message' => 'Resume updated successfully',
        'resume' => $resume
    ]);
}
public function show(Request $request, $id)
{
    $resume = Resume::where('user_id', $request->user()->id)
        ->findOrFail($id);

    return response()->json($resume);
}
}
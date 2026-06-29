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
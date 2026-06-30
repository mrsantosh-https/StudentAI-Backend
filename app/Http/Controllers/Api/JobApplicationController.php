<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;

class JobApplicationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->jobApplications()
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'status' => 'required|string',
            'location' => 'nullable|string',
            'job_link' => 'nullable|string',
            'applied_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $data['user_id'] = $request->user()->id;

        return JobApplication::create($data);
    }

    public function show(JobApplication $job)
    {
        return $job;
    }

    public function update(Request $request, JobApplication $job)
    {
        $job->update($request->all());

        return $job;
    }

    public function destroy(JobApplication $job)
    {
        $job->delete();

        return response()->json([
            'message' => 'Job deleted successfully'
        ]);
    }
}
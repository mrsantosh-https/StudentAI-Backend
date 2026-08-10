<?php

namespace App\Http\Controllers\Api;

use App\Models\Resume;
use Illuminate\Http\Request;
use App\Models\ResumeVersion;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResumeController extends Controller
{
    public function index(Request $request)
    {
        $resumes = Resume::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'resumes' => $resumes,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateResume($request);

            $resume = Resume::create([
                'user_id' => $request->user()->id,
                ...$this->prepareResumeData($validated),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Resume saved successfully.',
                'resume' => $resume,
            ], 201);
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            Log::error('Resume store error', [
                'message' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Resume $resume)
    {
        $this->authorizeResume($request, $resume);

        return response()->json([
            'success' => true,
            'resume' => $resume,
        ]);
    }

   public function update(Request $request, Resume $resume)
        {
            try {
                $this->authorizeResume($request, $resume);

                $validated = $this->validateResume($request);

                /*
                |--------------------------------------------------------------------------
                | Save current resume as old version BEFORE updating
                |--------------------------------------------------------------------------
                */

                $latestVersion = ResumeVersion::where(
                    'resume_id',
                    $resume->id
                )->max('version_number');

                $nextVersion = ($latestVersion ?? 0) + 1;

                ResumeVersion::create([
                    'resume_id' => $resume->id,
                    'user_id' => $request->user()->id,
                    'version_number' => $nextVersion,

                    'title' => $resume->title,
                    'full_name' => $resume->full_name,
                    'designation' => $resume->designation,
                    'email' => $resume->email,
                    'phone' => $resume->phone,

                    'address' => $resume->address,
                    'city' => $resume->city,
                    'state' => $resume->state,
                    'country' => $resume->country,
                    'pincode' => $resume->pincode,

                    'linkedin' => $resume->linkedin,
                    'github' => $resume->github,
                    'portfolio' => $resume->portfolio,

                    'career_objective' => $resume->career_objective,
                    'summary' => $resume->summary,
                    'education' => $resume->education,
                    'skills' => $resume->skills,
                    'projects' => $resume->projects,
                    'experience' => $resume->experience,

                    'template' => $resume->template ?? 'modern',
                    'ats_score' => $resume->ats_score,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Update main resume
                |--------------------------------------------------------------------------
                */

                $resume->update(
                    $this->prepareResumeData($validated)
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Resume updated successfully.',
                    'resume' => $resume->fresh(),
                    'version_created' => $nextVersion,
                ]);

            } catch (ValidationException $error) {
                throw $error;

            } catch (\Throwable $error) {
                Log::error('Resume update error', [
                    'message' => $error->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $error->getMessage(),
                ], 500);
            }
        }

    public function destroy(Request $request, Resume $resume)
    {
        $this->authorizeResume($request, $resume);

        $resume->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resume deleted successfully.',
        ]);
    }

    public function analytics(Request $request)
{
    try {
        $userId = $request->user()->id;

        $resumes = Resume::where('user_id', $userId);

        $totalResumes = (clone $resumes)->count();

        $averageAtsScore = (clone $resumes)
            ->whereNotNull('ats_score')
            ->avg('ats_score');

        $latestResume = (clone $resumes)
            ->latest()
            ->first();

        $atsScores = Resume::where('user_id', $userId)
            ->whereNotNull('ats_score')
            ->latest()
            ->take(10)
            ->get([
                'id',
                'title',
                'ats_score',
                'created_at',
            ]);

        return response()->json([
            'success' => true,

            'total_resumes' => $totalResumes,

            'average_ats_score' => round(
                (float) ($averageAtsScore ?? 0),
                2
            ),

            'latest_resume' => $latestResume,

            'ats_scores' => $atsScores,
        ]);

    } catch (\Throwable $error) {

        \Log::error('Dashboard analytics error', [
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $error->getMessage(),
        ], 500);
    }
}

    private function validateResume(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'fullName' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'linkedin' => ['nullable', 'url', 'max:500'],
            'github' => ['nullable', 'url', 'max:500'],
            'portfolio' => ['nullable', 'url', 'max:500'],
            'careerObjective' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'projects' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
            'template' => [
                'nullable',
                'string',
                'in:modern,classic,minimal,creative',
            ],
        ]);
    }

    private function prepareResumeData(array $validated): array
    {
        return [
            'title' => $validated['title'] ?? 'My Resume',
            'full_name' => $validated['fullName'],
            'designation' => $validated['designation'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'linkedin' => $validated['linkedin'] ?? null,
            'github' => $validated['github'] ?? null,
            'portfolio' => $validated['portfolio'] ?? null,
            'career_objective' => $validated['careerObjective'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'education' => $validated['education'] ?? null,
            'skills' => $validated['skills'] ?? null,
            'projects' => $validated['projects'] ?? null,
            'experience' => $validated['experience'] ?? null,
            'template' => $validated['template'] ?? 'modern',
        ];
    }

    private function authorizeResume(Request $request, Resume $resume): void
    {
        abort_if(
            $resume->user_id !== $request->user()->id,
            403,
            'You are not allowed to access this resume.'
        );
    }

    public function versions(Request $request, Resume $resume)
{
    $this->authorizeResume($request, $resume);

    $versions = ResumeVersion::where('resume_id', $resume->id)
        ->where('user_id', $request->user()->id)
        ->latest('version_number')
        ->get();

    return response()->json([
        'success' => true,
        'versions' => $versions,
    ]);
}

public function restoreVersion(
    Request $request,
    Resume $resume,
    ResumeVersion $version
) {
    $this->authorizeResume($request, $resume);

    if (
        $version->resume_id !== $resume->id ||
        $version->user_id !== $request->user()->id
    ) {
        abort(403, 'You are not allowed to restore this version.');
    }

    try {
        /*
        |--------------------------------------------------------------------------
        | Current resume ko pehle snapshot me save karo
        |--------------------------------------------------------------------------
        */

        $latestVersion = ResumeVersion::where(
            'resume_id',
            $resume->id
        )->max('version_number');

        $nextVersion = ($latestVersion ?? 0) + 1;

        ResumeVersion::create([
            'resume_id' => $resume->id,
            'user_id' => $request->user()->id,
            'version_number' => $nextVersion,

            'title' => $resume->title,
            'full_name' => $resume->full_name,
            'designation' => $resume->designation,
            'email' => $resume->email,
            'phone' => $resume->phone,

            'address' => $resume->address,
            'city' => $resume->city,
            'state' => $resume->state,
            'country' => $resume->country,
            'pincode' => $resume->pincode,

            'linkedin' => $resume->linkedin,
            'github' => $resume->github,
            'portfolio' => $resume->portfolio,

            'career_objective' => $resume->career_objective,
            'summary' => $resume->summary,
            'education' => $resume->education,
            'skills' => $resume->skills,
            'projects' => $resume->projects,
            'experience' => $resume->experience,

            'template' => $resume->template ?? 'modern',
            'ats_score' => $resume->ats_score,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Selected version restore karo
        |--------------------------------------------------------------------------
        */

        $resume->update([
            'title' => $version->title,
            'full_name' => $version->full_name,
            'designation' => $version->designation,
            'email' => $version->email,
            'phone' => $version->phone,

            'address' => $version->address,
            'city' => $version->city,
            'state' => $version->state,
            'country' => $version->country,
            'pincode' => $version->pincode,

            'linkedin' => $version->linkedin,
            'github' => $version->github,
            'portfolio' => $version->portfolio,

            'career_objective' => $version->career_objective,
            'summary' => $version->summary,
            'education' => $version->education,
            'skills' => $version->skills,
            'projects' => $version->projects,
            'experience' => $version->experience,

            'template' => $version->template ?? 'modern',
            'ats_score' => $version->ats_score,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Resume version restored successfully.',
            'resume' => $resume->fresh(),
        ]);
    } catch (\Throwable $error) {
        Log::error('Resume version restore error', [
            'message' => $error->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $error->getMessage(),
        ], 500);
    }
}

public function deleteVersion(
    Request $request,
    Resume $resume,
    ResumeVersion $version
) {
    $this->authorizeResume($request, $resume);

    if (
        $version->resume_id !== $resume->id ||
        $version->user_id !== $request->user()->id
    ) {
        abort(403, 'You are not allowed to delete this version.');
    }

    $version->delete();

    return response()->json([
        'success' => true,
        'message' => 'Resume version deleted successfully.',
    ]);
}
}
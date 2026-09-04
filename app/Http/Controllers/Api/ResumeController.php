<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Models\ResumeVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumeController extends Controller
{
    /**
     * ============================================================
     * LIST USER RESUMES
     * ============================================================
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $resumes = Resume::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'resumes' => $resumes,
        ], 200);
    }

    /**
     * ============================================================
     * SHOW SINGLE RESUME
     * ============================================================
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $resume = Resume::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$resume) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'resume' => $resume,
        ], 200);
    }

    /**
     * ============================================================
     * CREATE RESUME
     * ============================================================
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /**
         * --------------------------------------------------------
         * VALIDATION
         * --------------------------------------------------------
         */
        $validated = $request->validate([
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'template' => [
                'required',
                'string',
                'max:100',
            ],

            'fullName' => [
                'required',
                'string',
                'max:255',
            ],

            'designation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'pincode' => [
                'nullable',
                'string',
                'max:20',
            ],

            'linkedin' => [
                'nullable',
                'string',
                'max:500',
            ],

            'github' => [
                'nullable',
                'string',
                'max:500',
            ],

            'portfolio' => [
                'nullable',
                'string',
                'max:500',
            ],

            'careerObjective' => [
                'nullable',
                'string',
            ],

            'summary' => [
                'nullable',
                'string',
            ],

            'education' => [
                'nullable',
            ],

            'skills' => [
                'nullable',
            ],

            'projects' => [
                'nullable',
            ],

            'experience' => [
                'nullable',
            ],
        ]);

        /**
         * --------------------------------------------------------
         * CREATE
         * --------------------------------------------------------
         */
        try {
            $resume = Resume::create([
                'user_id' => $user->id,

                'title' =>
                    $validated['title']
                    ?? ($validated['fullName'] . ' Resume'),

                'template' =>
                    $validated['template'],

                'full_name' =>
                    $validated['fullName'],

                'designation' =>
                    $validated['designation'] ?? null,

                'email' =>
                    $validated['email'],

                'phone' =>
                    $validated['phone'] ?? null,

                'address' =>
                    $validated['address'] ?? null,

                'city' =>
                    $validated['city'] ?? null,

                'state' =>
                    $validated['state'] ?? null,

                'country' =>
                    $validated['country'] ?? null,

                'pincode' =>
                    $validated['pincode'] ?? null,

                'linkedin' =>
                    $validated['linkedin'] ?? null,

                'github' =>
                    $validated['github'] ?? null,

                'portfolio' =>
                    $validated['portfolio'] ?? null,

                'career_objective' =>
                    $validated['careerObjective'] ?? null,

                'summary' =>
                    $validated['summary'] ?? null,

                'education' =>
                    $validated['education'] ?? null,

                'skills' =>
                    $validated['skills'] ?? null,

                'projects' =>
                    $validated['projects'] ?? null,

                'experience' =>
                    $validated['experience'] ?? null,
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume created successfully.',

                'resume' =>
                    $resume,
            ], 201);

        } catch (Throwable $e) {

            Log::error(
                'Resume creation failed.',
                [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to create resume.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * ============================================================
     * UPDATE RESUME
     * ============================================================
     *
     * Before updating the resume, the current resume data is saved
     * into resume_versions table.
     *
     * Example:
     *
     * Current Resume = Version 1
     * User edits resume
     * Old data -> Version 1
     * Updated resume -> Current Resume
     *
     * Next edit:
     * Current data -> Version 2
     * Updated resume -> Current Resume
     *
     * ============================================================
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /**
         * --------------------------------------------------------
         * FIND USER'S RESUME
         * --------------------------------------------------------
         */
        $resume = Resume::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$resume) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        /**
         * --------------------------------------------------------
         * VALIDATION
         * --------------------------------------------------------
         */
        $validated = $request->validate([
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'template' => [
                'required',
                'string',
                'max:100',
            ],

            'fullName' => [
                'required',
                'string',
                'max:255',
            ],

            'designation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'pincode' => [
                'nullable',
                'string',
                'max:20',
            ],

            'linkedin' => [
                'nullable',
                'string',
                'max:500',
            ],

            'github' => [
                'nullable',
                'string',
                'max:500',
            ],

            'portfolio' => [
                'nullable',
                'string',
                'max:500',
            ],

            'careerObjective' => [
                'nullable',
                'string',
            ],

            'summary' => [
                'nullable',
                'string',
            ],

            'education' => [
                'nullable',
            ],

            'skills' => [
                'nullable',
            ],

            'projects' => [
                'nullable',
            ],

            'experience' => [
                'nullable',
            ],
        ]);

        /**
         * --------------------------------------------------------
         * UPDATE + CREATE VERSION
         * --------------------------------------------------------
         */
        try {

            DB::transaction(function () use (
                $resume,
                $user,
                $validated
            ) {

                /**
                 * ------------------------------------------------
                 * GET NEXT VERSION NUMBER
                 * ------------------------------------------------
                 */
                $lastVersion = ResumeVersion::query()
                    ->where('resume_id', $resume->id)
                    ->where('user_id', $user->id)
                    ->max('version_number');

                $nextVersion =
                    ($lastVersion ?? 0) + 1;

                /**
                 * ------------------------------------------------
                 * SAVE CURRENT RESUME AS VERSION
                 * ------------------------------------------------
                 */
                ResumeVersion::create([
                    'resume_id' =>
                        $resume->id,

                    'user_id' =>
                        $user->id,

                    'version_number' =>
                        $nextVersion,

                    'title' =>
                        $resume->title,

                    'full_name' =>
                        $resume->full_name,

                    'designation' =>
                        $resume->designation,

                    'email' =>
                        $resume->email,

                    'phone' =>
                        $resume->phone,

                    'address' =>
                        $resume->address,

                    'city' =>
                        $resume->city,

                    'state' =>
                        $resume->state,

                    'country' =>
                        $resume->country,

                    'pincode' =>
                        $resume->pincode,

                    'linkedin' =>
                        $resume->linkedin,

                    'github' =>
                        $resume->github,

                    'portfolio' =>
                        $resume->portfolio,

                    'career_objective' =>
                        $resume->career_objective,

                    'summary' =>
                        $resume->summary,

                    'education' =>
                        $resume->education,

                    'skills' =>
                        $resume->skills,

                    'projects' =>
                        $resume->projects,

                    'experience' =>
                        $resume->experience,

                    'template' =>
                        $resume->template,

                    'ats_score' =>
                        $resume->ats_score ?? null,
                ]);

                /**
                 * ------------------------------------------------
                 * UPDATE CURRENT RESUME
                 * ------------------------------------------------
                 */
                $resume->update([
                    'title' =>
                        $validated['title']
                        ?? ($validated['fullName'] . ' Resume'),

                    'template' =>
                        $validated['template'],

                    'full_name' =>
                        $validated['fullName'],

                    'designation' =>
                        $validated['designation'] ?? null,

                    'email' =>
                        $validated['email'],

                    'phone' =>
                        $validated['phone'] ?? null,

                    'address' =>
                        $validated['address'] ?? null,

                    'city' =>
                        $validated['city'] ?? null,

                    'state' =>
                        $validated['state'] ?? null,

                    'country' =>
                        $validated['country'] ?? null,

                    'pincode' =>
                        $validated['pincode'] ?? null,

                    'linkedin' =>
                        $validated['linkedin'] ?? null,

                    'github' =>
                        $validated['github'] ?? null,

                    'portfolio' =>
                        $validated['portfolio'] ?? null,

                    'career_objective' =>
                        $validated['careerObjective'] ?? null,

                    'summary' =>
                        $validated['summary'] ?? null,

                    'education' =>
                        $validated['education'] ?? null,

                    'skills' =>
                        $validated['skills'] ?? null,

                    'projects' =>
                        $validated['projects'] ?? null,

                    'experience' =>
                        $validated['experience'] ?? null,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume updated successfully.',

                'resume' =>
                    $resume->fresh(),
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Resume update failed.',
                [
                    'user_id' =>
                        $user->id,

                    'resume_id' =>
                        $resume->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to update resume.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * ============================================================
     * GET RESUME VERSION HISTORY
     * ============================================================
     */
    public function versions(Request $request, $resume)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /**
         * Find resume belonging to logged-in user
         */
        $resumeModel = Resume::query()
            ->where('id', $resume)
            ->where('user_id', $user->id)
            ->first();

        if (!$resumeModel) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        /**
         * Get all versions
         */
        $versions = ResumeVersion::query()
            ->where('resume_id', $resumeModel->id)
            ->where('user_id', $user->id)
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'success' => true,
            'versions' => $versions,
        ], 200);
    }

    /**
     * ============================================================
     * RESTORE RESUME VERSION
     * ============================================================
     */
    public function restoreVersion(
        Request $request,
        $resume,
        $version
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /**
         * Find user's resume
         */
        $resumeModel = Resume::query()
            ->where('id', $resume)
            ->where('user_id', $user->id)
            ->first();

        if (!$resumeModel) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        /**
         * Find version belonging to this resume/user
         */
        $versionModel = ResumeVersion::query()
            ->where('id', $version)
            ->where('resume_id', $resumeModel->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$versionModel) {
            return response()->json([
                'success' => false,
                'message' => 'Resume version not found.',
            ], 404);
        }

        try {

            DB::transaction(function () use (
                $resumeModel,
                $versionModel,
                $user
            ) {

                /**
                 * -----------------------------------------------
                 * SAVE CURRENT RESUME BEFORE RESTORING
                 * -----------------------------------------------
                 *
                 * This ensures restore itself is also reversible.
                 */
                $lastVersion = ResumeVersion::query()
                    ->where('resume_id', $resumeModel->id)
                    ->where('user_id', $user->id)
                    ->max('version_number');

                $nextVersion =
                    ($lastVersion ?? 0) + 1;

                ResumeVersion::create([
                    'resume_id' =>
                        $resumeModel->id,

                    'user_id' =>
                        $user->id,

                    'version_number' =>
                        $nextVersion,

                    'title' =>
                        $resumeModel->title,

                    'full_name' =>
                        $resumeModel->full_name,

                    'designation' =>
                        $resumeModel->designation,

                    'email' =>
                        $resumeModel->email,

                    'phone' =>
                        $resumeModel->phone,

                    'address' =>
                        $resumeModel->address,

                    'city' =>
                        $resumeModel->city,

                    'state' =>
                        $resumeModel->state,

                    'country' =>
                        $resumeModel->country,

                    'pincode' =>
                        $resumeModel->pincode,

                    'linkedin' =>
                        $resumeModel->linkedin,

                    'github' =>
                        $resumeModel->github,

                    'portfolio' =>
                        $resumeModel->portfolio,

                    'career_objective' =>
                        $resumeModel->career_objective,

                    'summary' =>
                        $resumeModel->summary,

                    'education' =>
                        $resumeModel->education,

                    'skills' =>
                        $resumeModel->skills,

                    'projects' =>
                        $resumeModel->projects,

                    'experience' =>
                        $resumeModel->experience,

                    'template' =>
                        $resumeModel->template,

                    'ats_score' =>
                        $resumeModel->ats_score ?? null,
                ]);

                /**
                 * -----------------------------------------------
                 * RESTORE SELECTED VERSION
                 * -----------------------------------------------
                 */
                $resumeModel->update([
                    'title' =>
                        $versionModel->title,

                    'full_name' =>
                        $versionModel->full_name,

                    'designation' =>
                        $versionModel->designation,

                    'email' =>
                        $versionModel->email,

                    'phone' =>
                        $versionModel->phone,

                    'address' =>
                        $versionModel->address,

                    'city' =>
                        $versionModel->city,

                    'state' =>
                        $versionModel->state,

                    'country' =>
                        $versionModel->country,

                    'pincode' =>
                        $versionModel->pincode,

                    'linkedin' =>
                        $versionModel->linkedin,

                    'github' =>
                        $versionModel->github,

                    'portfolio' =>
                        $versionModel->portfolio,

                    'career_objective' =>
                        $versionModel->career_objective,

                    'summary' =>
                        $versionModel->summary,

                    'education' =>
                        $versionModel->education,

                    'skills' =>
                        $versionModel->skills,

                    'projects' =>
                        $versionModel->projects,

                    'experience' =>
                        $versionModel->experience,

                    'template' =>
                        $versionModel->template,

                    'ats_score' =>
                        $versionModel->ats_score,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume version restored successfully.',

                'resume' =>
                    $resumeModel->fresh(),
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Resume version restore failed.',
                [
                    'user_id' =>
                        $user->id,

                    'resume_id' =>
                        $resumeModel->id,

                    'version_id' =>
                        $versionModel->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to restore resume version.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * ============================================================
     * DELETE RESUME VERSION
     * ============================================================
     */
    public function deleteVersion(
        Request $request,
        $resume,
        $version
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /**
         * Find version belonging to user's resume
         */
        $versionModel = ResumeVersion::query()
            ->where('id', $version)
            ->where('resume_id', $resume)
            ->where('user_id', $user->id)
            ->first();

        if (!$versionModel) {
            return response()->json([
                'success' => false,
                'message' => 'Resume version not found.',
            ], 404);
        }

        try {

            $versionModel->delete();

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume version deleted successfully.',
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Resume version deletion failed.',
                [
                    'user_id' =>
                        $user->id,

                    'resume_id' =>
                        $resume,

                    'version_id' =>
                        $version,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to delete resume version.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * ============================================================
     * DELETE RESUME
     * ============================================================
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $resume = Resume::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$resume) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found.',
            ], 404);
        }

        try {

            $resume->delete();

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume deleted successfully.',
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Resume deletion failed.',
                [
                    'user_id' =>
                        $user->id,

                    'resume_id' =>
                        $resume->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to delete resume.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }
}
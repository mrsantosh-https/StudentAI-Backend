<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumeController extends Controller
{
    protected SubscriptionLimitService $subscriptionLimit;

    public function __construct(
        SubscriptionLimitService $subscriptionLimit
    ) {
        $this->subscriptionLimit = $subscriptionLimit;
    }

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

        $usage = $this->subscriptionLimit->getUsage(
            $user,
            'resume'
        );

        return response()->json([
            'success' => true,
            'resumes' => $resumes,

            'usage' => $usage,
        ], 200);
    }

    /**
     * ============================================================
     * SHOW SINGLE RESUME
     * ============================================================
     */
    public function show(
        Request $request,
        $id
    ) {
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

        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION LIMIT CHECK
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | This check is done on backend.
        | Frontend cannot bypass this.
        |
        */
        try {
            $this->subscriptionLimit->check(
                $user,
                'resume'
            );
        } catch (Throwable $e) {

            Log::warning(
                'Resume subscription limit reached.',
                [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'RESUME_LIMIT_REACHED',

                'usage' => $this->subscriptionLimit->getUsage(
                    $user,
                    'resume'
                ),
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | CREATE RESUME
        |--------------------------------------------------------------------------
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

            /*
            |--------------------------------------------------------------------------
            | UPDATED USAGE
            |--------------------------------------------------------------------------
            */

            $usage = $this->subscriptionLimit->getUsage(
                $user,
                'resume'
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume created successfully.',

                'resume' =>
                    $resume,

                'usage' =>
                    $usage,
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
     */
    public function update(
        Request $request,
        $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | FIND USER'S RESUME
        |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        try {

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

            return response()->json([
                'success' => true,

                'message' =>
                    'Resume updated successfully.',

                'resume' =>
                    $resume->fresh(),

                'usage' =>
                    $this->subscriptionLimit->getUsage(
                        $user,
                        'resume'
                    ),
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Resume update failed.',
                [
                    'user_id' => $user->id,
                    'resume_id' => $resume->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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
     * DELETE RESUME
     * ============================================================
     */
    public function destroy(
        Request $request,
        $id
    ) {
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

                'usage' =>
                    $this->subscriptionLimit->getUsage(
                        $user,
                        'resume'
                    ),
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Resume deletion failed.',
                [
                    'user_id' => $user->id,
                    'resume_id' => $resume->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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

    /**
     * ============================================================
     * RESUME USAGE
     * ============================================================
     *
     * Optional endpoint:
     *
     * GET /api/resumes/usage
     */
    public function usage(Request $request)
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

            'usage' =>
                $this->subscriptionLimit->getUsage(
                    $user,
                    'resume'
                ),
        ], 200);
    }
}
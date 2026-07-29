<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Services\ResumeReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumeReviewController extends Controller
{
    public function __construct(
        private readonly ResumeReviewService $resumeReviewService
    ) {
    }

    public function review(Request $request, int $id): JsonResponse
    {
        try {
            $resume = Resume::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$resume) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resume not found.',
                ], 404);
            }

            $resumeData = [
                'title' => $resume->title ?? '',
                'full_name' => $resume->full_name ?? '',
                'email' => $resume->email ?? '',
                'phone' => $resume->phone ?? '',
                'summary' => $resume->summary ?? '',
                'skills' => $resume->skills ?? '',
                'experience' => $resume->experience ?? '',
                'education' => $resume->education ?? '',
                'projects' => $resume->projects ?? '',
            ];

            $review = $this->resumeReviewService->review($resumeData);

            return response()->json([
                'success' => true,
                'message' => 'AI resume review generated successfully.',
                'review' => $review,
            ]);
        } catch (Throwable $error) {
            Log::error('AI resume review failed', [
                'resume_id' => $id,
                'user_id' => $request->user()?->id,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $error->getMessage()
                    : 'AI resume review generate nahi ho saka.',
            ], 500);
        }
    }
}
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\ResumeAIController;
use App\Http\Controllers\Api\ResumeReviewController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CoverLetterController;
use App\Http\Controllers\Api\CareerRoadmapController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\JobMatcherController;
use App\Http\Controllers\Api\InterviewHistoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AiCareerCoachController;
use App\Http\Controllers\Api\MockInterviewController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::post('/forgot-password/resend-otp', [ForgotPasswordController::class, 'resendOtp']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return response()->json([
            'message' => 'Authenticated successfully',
            'user' => $request->user(),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'profile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadProfilePhoto']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::delete('/delete-account', [ProfileController::class, 'deleteAccount']);

    /*
    |--------------------------------------------------------------------------
    | Resume CRUD
    |--------------------------------------------------------------------------
    */

    Route::apiResource('resumes', ResumeController::class);

    Route::put('/resumes/{id}/ats-score', [ResumeController::class, 'updateAtsScore']);

    /*
    |--------------------------------------------------------------------------
    | Resume AI
    |--------------------------------------------------------------------------
    */

    Route::post('/ai/resume-summary', [ResumeAIController::class, 'resumeSummary']);
    Route::post('/resumes/{id}/analyze', [ResumeAIController::class, 'analyze']);
    Route::post('/resumes/{id}/improve', [ResumeAIController::class, 'improveResume']);
    Route::post('/resumes/{id}/review', [ResumeReviewController::class, 'review']);

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/analytics', [ResumeController::class, 'analytics']);
    Route::get('/dashboard-stats', [DashboardController::class, 'stats']);

    /*
    |--------------------------------------------------------------------------
    | AI Tools
    |--------------------------------------------------------------------------
    */

    Route::post('/ai/cover-letter', [CoverLetterController::class, 'generate']);
    Route::post('/ai/career-roadmap', [CareerRoadmapController::class, 'generate']);
    Route::post('/ai/job-match', [JobMatcherController::class, 'match']);

    /*
    |--------------------------------------------------------------------------
    | Jobs
    |--------------------------------------------------------------------------
    */

    Route::apiResource('jobs', JobApplicationController::class);

    /*
    |--------------------------------------------------------------------------
    | Interview History
    |--------------------------------------------------------------------------
    */

    Route::get('/interview-history', [InterviewHistoryController::class, 'index']);
    Route::post('/interview-history', [InterviewHistoryController::class, 'store']);
    Route::delete('/interview-history/{id}', [InterviewHistoryController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | AI Career Coach
    |--------------------------------------------------------------------------
    */

    Route::post('/ai-chat', [AiCareerCoachController::class, 'chat']);
    Route::get('/ai-chats', [AiCareerCoachController::class, 'history']);
    Route::delete('/ai-chats/clear-all', [AiCareerCoachController::class, 'clearAll']);
    Route::post('/ai-chats/{id}/feedback', [AiCareerCoachController::class, 'feedback']);
    Route::delete('/ai-chats/{id}', [AiCareerCoachController::class, 'deleteChat']);

    /*
    |--------------------------------------------------------------------------
    | Mock Interview
    |--------------------------------------------------------------------------
    */

    Route::post('/mock-interview/start', [MockInterviewController::class, 'start']);
    Route::post('/mock-interview/answer', [MockInterviewController::class, 'answer']);
    Route::get('/mock-interview/history', [MockInterviewController::class, 'history']);
    Route::delete('/mock-interview/history/{id}', [MockInterviewController::class, 'destroy']);

    Route::post(
    '/ai/interview-questions',
    [AIController::class, 'generateInterviewQuestions']
    );

    Route::post(
        '/ai/interview-feedback',
        [AIController::class, 'evaluateInterviewAnswer']
    );

});
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AiCareerCoachController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\InterviewHistoryController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\MockInterviewController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [
    ForgotPasswordController::class,
    'sendResetLink'
]);

Route::post('/reset-password', [
    ForgotPasswordController::class,
    'resetPassword'
]);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Test authenticated user
    Route::get('/user', function (Request $request) {
        return response()->json([
            'message' => 'Authenticated successfully',
            'user' => $request->user(),
        ]);
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'profile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadProfilePhoto']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::delete('/delete-account', [ProfileController::class, 'deleteAccount']);

    // Resumes
    Route::get('/resumes', [ResumeController::class, 'index']);
    Route::post('/resumes', [ResumeController::class, 'store']);
    Route::get('/resumes/{id}', [ResumeController::class, 'show']);
    Route::put('/resumes/{id}', [ResumeController::class, 'update']);
    Route::delete('/resumes/{id}', [ResumeController::class, 'destroy']);
    Route::put('/resumes/{id}/ats-score', [
        ResumeController::class,
        'updateAtsScore'
    ]);

    Route::get('/dashboard/analytics', [
        ResumeController::class,
        'analytics'
    ]);

    Route::get('/dashboard-stats', [
        DashboardController::class,
        'stats'
    ]);

    // Jobs
    Route::apiResource('jobs', JobApplicationController::class);

    // Interview history
    Route::get('/interview-history', [
        InterviewHistoryController::class,
        'index'
    ]);

    Route::post('/interview-history', [
        InterviewHistoryController::class,
        'store'
    ]);

    Route::delete('/interview-history/{id}', [
        InterviewHistoryController::class,
        'destroy'
    ]);

    // Notifications
    Route::get('/notifications', [
        NotificationController::class,
        'index'
    ]);

    Route::post('/notifications/read/{id}', [
        NotificationController::class,
        'markAsRead'
    ]);
     Route::post(
        '/notifications/read-all',
        [NotificationController::class, 'markAllAsRead']
    );

    Route::delete('/notifications/{id}', [
        NotificationController::class,
        'destroy'
    ]);

    // AI Career Coach
    Route::post('/ai-chat', [
        AiCareerCoachController::class,
        'chat'
    ]);

    Route::get('/ai-chats', [
        AiCareerCoachController::class,
        'history'
    ]);

    Route::delete('/ai-chats/{id}', [
        AiCareerCoachController::class,
        'deleteChat'
    ]);

    Route::post('/ai-chats/{id}/feedback', [
        AiCareerCoachController::class,
        'feedback'
    ]);

    // Mock Interview
    Route::post('/mock-interview/start', [
        MockInterviewController::class,
        'start'
    ]);

    Route::post('/mock-interview/answer', [
        MockInterviewController::class,
        'answer'
    ]);

    Route::get('/mock-interview/history', [
        MockInterviewController::class,
        'history'
    ]);
     Route::delete(
        '/mock-interview/history/{id}',
        [MockInterviewController::class, 'destroy']
    );
});
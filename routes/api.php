<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AiCareerCoachController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\InterviewHistoryController;
use App\Http\Controllers\Api\ForgotPasswordController;

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/resumes/{id}/ats-score', [ResumeController::class, 'updateAtsScore']);
    Route::post('/resumes', [ResumeController::class, 'store']);
    Route::get('/resumes', [ResumeController::class, 'index']);
    Route::get('/resumes/{id}', [ResumeController::class, 'show']);
    Route::put('/resumes/{id}', [ResumeController::class, 'update']);
    Route::delete('/resumes/{id}', [ResumeController::class, 'destroy']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [AuthController::class, 'uploadProfilePhoto']);
    Route::get('/dashboard/analytics', [ResumeController::class, 'analytics']);
    Route::apiResource('jobs', JobApplicationController::class);
    // Interview History
    Route::get('/interview-history', [InterviewHistoryController::class, 'index']);

    Route::post('/interview-history', [InterviewHistoryController::class, 'store']);

    Route::delete('/interview-history/{id}', [InterviewHistoryController::class, 'destroy']);

    Route::get('/profile', [ProfileController::class, 'profile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadProfilePhoto']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);

    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    Route::delete('/delete-account', [ProfileController::class, 'deleteAccount']);

    Route::middleware('auth:sanctum')->get('/dashboard-stats', [DashboardController::class, 'stats']);

    Route::middleware('auth:sanctum')->post('/ai-chat', [AiCareerCoachController::class, 'chat']);

Route::middleware('auth:sanctum')->post('/ai-chat', [AiCareerCoachController::class, 'chat']);
Route::middleware('auth:sanctum')->get('/ai-chats', [AiCareerCoachController::class, 'history']);
Route::middleware('auth:sanctum')->delete('/ai-chats/{id}', [AiCareerCoachController::class, 'deleteChat']);
Route::middleware('auth:sanctum')->post('/ai-chats/{id}/feedback', [AiCareerCoachController::class, 'feedback']);
});
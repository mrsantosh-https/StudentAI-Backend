<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\InterviewHistoryController;

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

});
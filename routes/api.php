<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Controllers
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResumeVersionController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AdminFeedbackController;
// use App\Http\Controllers\Api\SubscriptionPlanController;
// use App\Http\Controllers\AdminSubscriptionPlanController;
// use App\Http\Controllers\AdminUserSubscriptionController;
use App\Http\Controllers\Api\PaymentController;

use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\ResumeAIController;
use App\Http\Controllers\Api\ResumeReviewController;

use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\CoverLetterController;
use App\Http\Controllers\Api\CareerRoadmapController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\JobMatcherController;

use App\Http\Controllers\Api\InterviewHistoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AiCareerCoachController;
use App\Http\Controllers\Api\MockInterviewController;
use App\Http\Controllers\Api\NotificationPreferenceController;

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminUserAnalyticsController;
use App\Http\Controllers\Api\AdminAIUsageController;
use App\Http\Controllers\Api\AdminLoginActivityController;
use App\Http\Controllers\Api\AdminNotificationController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Register
|--------------------------------------------------------------------------
*/

Route::post('/register', [
    AuthController::class,
    'register',
]);


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

Route::post('/login', [
    AuthController::class,
    'login',
]);


/*
|--------------------------------------------------------------------------
| Forgot Password
|--------------------------------------------------------------------------
*/

Route::post('/forgot-password/send-otp', [
    ForgotPasswordController::class,
    'sendOtp',
]);

Route::post('/forgot-password/verify-otp', [
    ForgotPasswordController::class,
    'verifyOtp',
]);

Route::post('/forgot-password/reset-password', [
    ForgotPasswordController::class,
    'resetPassword',
]);

Route::post('/forgot-password/resend-otp', [
    ForgotPasswordController::class,
    'resendOtp',
]);


/*
|--------------------------------------------------------------------------
| User Subscription Plans
|--------------------------------------------------------------------------
*/

// Route::get('/subscription-plans', [
//     SubscriptionPlanController::class,
//     'index',
// ]);


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Contact Form
    |--------------------------------------------------------------------------
    |
    | Only logged-in users can send contact messages.
    | user_id will come from authenticated Sanctum user.
    |
    */

    Route::post('/contact', [
        ContactMessageController::class,
        'store',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('admin')
        ->prefix('admin')
        ->group(function () {


            /*
            |--------------------------------------------------------------------------
            | Admin Dashboard
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [
                AdminDashboardController::class,
                'stats',
            ]);


            /*
            |--------------------------------------------------------------------------
            | User Analytics
            |--------------------------------------------------------------------------
            */

            Route::get('/user-analytics', [
                AdminUserAnalyticsController::class,
                'index',
            ]);


            /*
            |--------------------------------------------------------------------------
            | AI Usage Analytics
            |--------------------------------------------------------------------------
            */

            Route::get('/ai-usage', [
                AdminAIUsageController::class,
                'index',
            ]);

            Route::get('/ai-usage/{aiUsage}', [
                AdminAIUsageController::class,
                'show',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Login Activities
            |--------------------------------------------------------------------------
            */

            Route::get('/login-activities', [
                AdminLoginActivityController::class,
                'index',
            ]);

            Route::get('/login-activities/{loginActivity}', [
                AdminLoginActivityController::class,
                'show',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Admin Notifications
            |--------------------------------------------------------------------------
            */

            Route::get('/notifications', [
                AdminNotificationController::class,
                'index',
            ]);

            Route::post('/notifications', [
                AdminNotificationController::class,
                'store',
            ]);

            Route::post('/notifications/broadcast', [
                AdminNotificationController::class,
                'broadcast',
            ]);

            Route::get('/notifications/{notification}', [
                AdminNotificationController::class,
                'show',
            ]);

            Route::patch('/notifications/{notification}/read', [
                AdminNotificationController::class,
                'markAsRead',
            ]);

            Route::delete('/notifications/{notification}', [
                AdminNotificationController::class,
                'destroy',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Admin Feedback
            |--------------------------------------------------------------------------
            */

            Route::get('/feedback', [
                AdminFeedbackController::class,
                'index',
            ]);

            Route::get('/feedback/{id}', [
                AdminFeedbackController::class,
                'show',
            ]);

            Route::delete('/feedback/{id}', [
                AdminFeedbackController::class,
                'destroy',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Contact Messages - ADMIN ONLY
            |--------------------------------------------------------------------------
            */

            Route::get('/contact-messages', [
                ContactMessageController::class,
                'index',
            ]);

            Route::get('/contact-messages/{id}', [
                ContactMessageController::class,
                'show',
            ]);

            Route::patch('/contact-messages/{id}/status', [
                ContactMessageController::class,
                'updateStatus',
            ]);

            Route::delete('/contact-messages/{id}', [
                ContactMessageController::class,
                'destroy',
            ]);


            /*
            |--------------------------------------------------------------------------
            | User Management
            |--------------------------------------------------------------------------
            */

            Route::get('/users', [
                AdminUserController::class,
                'index',
            ]);

            Route::get('/users/{user}', [
                AdminUserController::class,
                'show',
            ]);

            Route::patch('/users/{user}/block', [
                AdminUserController::class,
                'block',
            ]);

            Route::patch('/users/{user}/unblock', [
                AdminUserController::class,
                'unblock',
            ]);

            Route::patch('/users/{user}/role', [
                AdminUserController::class,
                'updateRole',
            ]);

            Route::delete('/users/{user}', [
                AdminUserController::class,
                'destroy',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Subscription Plans - ADMIN
            |--------------------------------------------------------------------------
            */

        //     Route::get('/subscriptions', [
        //         AdminSubscriptionPlanController::class,
        //         'index',
        //     ]);

        //     Route::post('/subscriptions', [
        //         AdminSubscriptionPlanController::class,
        //         'store',
        //     ]);

        //     Route::get('/subscriptions/{subscriptionPlan}', [
        //         AdminSubscriptionPlanController::class,
        //         'show',
        //     ]);

        //     Route::put('/subscriptions/{subscriptionPlan}', [
        //         AdminSubscriptionPlanController::class,
        //         'update',
        //     ]);

        //     Route::patch('/subscriptions/{subscriptionPlan}', [
        //         AdminSubscriptionPlanController::class,
        //         'update',
        //     ]);

        //     Route::patch(
        //         '/subscriptions/{subscriptionPlan}/toggle',
        //         [
        //             AdminSubscriptionPlanController::class,
        //             'toggleStatus',
        //         ]
        //     );

        //     Route::delete('/subscriptions/{subscriptionPlan}', [
        //         AdminSubscriptionPlanController::class,
        //         'destroy',
        //     ]);


        //     /*
        //     |--------------------------------------------------------------------------
        //     | User Subscriptions
        //     |--------------------------------------------------------------------------
        //     */

        //     Route::get('/user-subscriptions', [
        //         AdminUserSubscriptionController::class,
        //         'index',
        //     ]);

        //     Route::get('/user-subscriptions/{userSubscription}', [
        //         AdminUserSubscriptionController::class,
        //         'show',
        //     ]);

        //     Route::post('/users/{user}/subscription', [
        //         AdminUserSubscriptionController::class,
        //         'assign',
        //     ]);

        //     Route::patch(
        //         '/user-subscriptions/{userSubscription}/cancel',
        //         [
        //             AdminUserSubscriptionController::class,
        //             'cancel',
        //         ]
        //     );

        //     Route::delete(
        //         '/user-subscriptions/{userSubscription}',
        //         [
        //             AdminUserSubscriptionController::class,
        //             'destroy',
        //         ]
        //     );
        });


    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [
        ProfileController::class,
        'profile',
    ]);

    Route::put('/profile', [
        ProfileController::class,
        'updateProfile',
    ]);

    Route::post('/profile/photo', [
        ProfileController::class,
        'uploadProfilePhoto',
    ]);

    Route::delete('/profile/photo', [
        ProfileController::class,
        'removeProfilePhoto',
    ]);

    Route::post('/change-password', [
        ProfileController::class,
        'changePassword',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Delete Account
    |--------------------------------------------------------------------------
    */

    Route::delete('/delete-account', [
        AuthController::class,
        'deleteAccount',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Resume CRUD
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'resumes',
        ResumeController::class
    );

    Route::put('/resumes/{id}/ats-score', [
        ResumeController::class,
        'updateAtsScore',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Resume AI
    |--------------------------------------------------------------------------
    */

    Route::post('/ai/resume-summary', [
        ResumeAIController::class,
        'resumeSummary',
    ]);

    Route::post('/resumes/{id}/analyze', [
        ResumeAIController::class,
        'analyze',
    ]);

    Route::post('/resumes/{id}/improve', [
        ResumeAIController::class,
        'improveResume',
    ]);

    Route::post('/resumes/{id}/review', [
        ResumeReviewController::class,
        'review',
    ]);

    Route::get('/resumes/{resume}/versions', [
        ResumeController::class,
        'versions',
    ]);

    Route::post(
        '/resumes/{resume}/versions/{version}/restore',
        [
            ResumeController::class,
            'restoreVersion',
        ]
    );

    Route::delete(
        '/resumes/{resume}/versions/{version}',
        [
            ResumeController::class,
            'deleteVersion',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/analytics', [
        DashboardController::class,
        'analytics',
    ]);

    Route::get('/dashboard-stats', [
        DashboardController::class,
        'stats',
    ]);


    /*
    |--------------------------------------------------------------------------
    | AI Tools
    |--------------------------------------------------------------------------
    */

    Route::post('/ai/cover-letter', [
        CoverLetterController::class,
        'generate',
    ]);

    Route::post('/ai/career-roadmap', [
        CareerRoadmapController::class,
        'generate',
    ]);

    Route::post('/ai/job-match', [
        JobMatcherController::class,
        'match',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Jobs
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'jobs',
        JobApplicationController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Interview History
    |--------------------------------------------------------------------------
    */

    Route::get('/interview-history', [
        InterviewHistoryController::class,
        'index',
    ]);

    Route::post('/interview-history', [
        InterviewHistoryController::class,
        'store',
    ]);

    Route::delete('/interview-history/{id}', [
        InterviewHistoryController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | User Notifications
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [
        NotificationController::class,
        'index',
    ]);

    Route::post('/notifications/read/{id}', [
        NotificationController::class,
        'markAsRead',
    ]);

    Route::post('/notifications/read-all', [
        NotificationController::class,
        'markAllAsRead',
    ]);

    Route::delete('/notifications/{id}', [
        NotificationController::class,
        'destroy',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Notification Preferences
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notification-preferences',
        [NotificationPreferenceController::class, 'index']
    );

    Route::put(
        '/notification-preferences',
        [NotificationPreferenceController::class, 'update']
    );

    Route::post(
        '/notification-preferences/reset',
        [NotificationPreferenceController::class, 'reset']
    );


    /*
    |--------------------------------------------------------------------------
    | AI Career Coach
    |--------------------------------------------------------------------------
    */

    Route::post('/ai-chat', [
        AiCareerCoachController::class,
        'chat',
    ]);

    Route::get('/ai-chats', [
        AiCareerCoachController::class,
        'history',
    ]);

    Route::delete('/ai-chats/clear-all', [
        AiCareerCoachController::class,
        'clearAll',
    ]);

    Route::post('/ai-chats/{id}/feedback', [
        AiCareerCoachController::class,
        'feedback',
    ]);

    Route::delete('/ai-chats/{id}', [
        AiCareerCoachController::class,
        'deleteChat',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Mock Interview
    |--------------------------------------------------------------------------
    */

    Route::post('/mock-interview/start', [
        MockInterviewController::class,
        'start',
    ]);

    Route::post('/mock-interview/answer', [
        MockInterviewController::class,
        'answer',
    ]);

    Route::get('/mock-interview/history', [
        MockInterviewController::class,
        'history',
    ]);

    Route::delete('/mock-interview/history/{id}', [
        MockInterviewController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Interview AI
    |--------------------------------------------------------------------------
    */

    Route::post('/ai/interview-questions', [
        AIController::class,
        'generateInterviewQuestions',
    ]);

    Route::post('/ai/interview-feedback', [
        AIController::class,
        'evaluateInterviewAnswer',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::post('/payment/create-order', [
        PaymentController::class,
        'createOrder',
    ]);

    Route::post('/payment/verify', [
        PaymentController::class,
        'verifyPayment',
    ]);

    Route::get('/payment/history', [
        PaymentController::class,
        'history',
    ]);

    Route::get('/my-subscription', [
        PaymentController::class,
        'mySubscription',
    ]);
});
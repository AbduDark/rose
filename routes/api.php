<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Health check endpoint
Route::get('/', function () {
    return response()->json([
        'message' => 'Rose Academy API is running',
        'version' => '1.0.0',
        'timestamp' => now(),
        'status' => 'active'
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;

// Public Routes
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::get('/verify-email',    [AuthController::class, 'verifyEmail']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('force-logout',    [AuthController::class, 'forceLogout']);
    Route::post('/resend-pin', [AuthController::class, 'resendPin']);
});

// Guest-accessible courses
Route::get('courses',       [CourseController::class, 'index']);
Route::get('courses/{id}',  [CourseController::class, 'show']);
Route::get('courses/{id}/ratings', [RatingController::class, 'index']);

// Authenticated User Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::get('profile',     [AuthController::class, 'profile']);
        Route::post('logout',     [AuthController::class, 'logout']);
        Route::put('update',      [AuthController::class, 'updateProfile']);
        Route::put('password',    [AuthController::class, 'changePassword']);
    });

    // Subscriptions
    Route::get('my-subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscribe',       [SubscriptionController::class, 'store']);
    Route::delete('unsubscribe/{courseId}', [SubscriptionController::class, 'destroy']);
    Route::post('renew-subscription/{courseId}', [SubscriptionController::class, 'renewSubscription']);

    // Favorites
    Route::post('favorite/{course_id}',    [FavoriteController::class, 'add']);
    Route::delete('favorite/{course_id}',  [FavoriteController::class, 'remove']);

    // Lessons & Comments
    Route::get('courses/{id}/lessons',         [LessonController::class, 'index']);
    Route::post('comments',                    [CommentController::class, 'store']);
    Route::get('lessons/{lesson_id}/comments', [CommentController::class, 'index']);

    // Ratings
    Route::post('ratings',                     [RatingController::class, 'store']);
    
    // Payments
    Route::get('courses/{courseId}/payment-form', [PaymentController::class, 'getPaymentForm']);
    Route::post('payments/vodafone',           [PaymentController::class, 'submitVodafonePayment']);
    Route::get('payments/history',             [PaymentController::class, 'getUserPaymentHistory']);
});

// Admin Routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Courses Management
    Route::post('courses',        [CourseController::class, 'store']);
    Route::put('courses/{id}',    [CourseController::class, 'update']);
    Route::delete('courses/{id}', [CourseController::class, 'destroy']);

    // Lessons Management
    Route::post('lessons',        [LessonController::class, 'store']);
    Route::put('lessons/{id}',    [LessonController::class, 'update']);
    Route::delete('lessons/{id}', [LessonController::class, 'destroy']);

    // Subscription Management
    Route::get('admin/subscriptions/pending', [SubscriptionController::class, 'pendingSubscriptions']);
    Route::put('admin/subscriptions/{id}/approve', [SubscriptionController::class, 'approveSubscription']);
    Route::put('admin/subscriptions/{id}/reject', [SubscriptionController::class, 'rejectSubscription']);

    // Payments Management
    Route::get('admin/payments/pending',      [PaymentController::class, 'getPendingPayments']);
    Route::get('admin/payments/stats',        [PaymentController::class, 'getPaymentStats']);
    Route::put('admin/payments/{id}/approve', [PaymentController::class, 'approvePayment']);
    Route::put('admin/payments/{id}/reject',  [PaymentController::class, 'rejectPayment']);

    // Comments Management
    Route::put('admin/comments/{id}/approve', [CommentController::class, 'approve']);
    Route::put('admin/comments/{id}/reject',  [CommentController::class, 'reject']);
    Route::get('admin/comments/pending',      [CommentController::class, 'pending']);

    // User Management
    Route::get('admin/users',         [UserController::class, 'index']);
    Route::put('admin/users/{id}',    [UserController::class, 'update']);
    Route::delete('admin/users/{id}', [UserController::class, 'destroy']);
});
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminController; // Import AdminController
use App\Http\Middleware\AdminMiddleware;

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
    Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
    Route::delete('subscriptions/{id}', [SubscriptionController::class, 'cancelSubscription']);

    // Comments
    Route::post('comments', [CommentController::class, 'store']);
    Route::get('lessons/{lessonId}/comments', [CommentController::class, 'getLessonComments']);
    Route::delete('comments/{id}', [CommentController::class, 'destroy']);

    // Favorites
    Route::post('favorite/{course_id}',    [FavoriteController::class, 'add']);
    Route::delete('favorite/{course_id}',  [FavoriteController::class, 'remove']);

    // Lessons & Comments
    Route::get('courses/{id}/lessons',         [LessonController::class, 'index']);
    // Route::post('comments',                    [CommentController::class, 'store']); // Already defined above
    // Route::get('lessons/{lesson_id}/comments', [CommentController::class, 'index']); // Already defined above

    // Ratings
    Route::post('ratings',                     [RatingController::class, 'store']);

    // Payments
    Route::get('courses/{courseId}/payment-form', [PaymentController::class, 'getPaymentForm']);
    Route::post('payments/vodafone',           [PaymentController::class, 'submitVodafonePayment']);
    Route::get('payments/history',             [PaymentController::class, 'getUserPaymentHistory']);
});

// Admin Routes (Protected)
Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->group(function () {
    // إدارة الكورسات
    Route::apiResource('courses', CourseController::class)->except(['index', 'show']);
    Route::apiResource('lessons', LessonController::class)->except(['index', 'show']);

    // إدارة المستخدمين
    Route::controller(App\Http\Controllers\Api\AdminController::class)->group(function () {
        Route::get('users', 'getUsers');
        Route::get('users/{id}', 'getUserDetails');
        Route::put('users/{id}', 'updateUser');
        Route::delete('users/{id}', 'deleteUser');

        // إحصائيات الدشبورد
        Route::get('dashboard/stats', 'getDashboardStats');

        // إدارة الاشتراكات
        Route::get('subscriptions', 'getSubscriptions');
        Route::get('subscriptions/pending', 'getPendingSubscriptions');
        Route::post('subscriptions/{id}/approve', 'approveSubscription');
        Route::post('subscriptions/{id}/reject', 'rejectSubscription');

        // إدارة التعليقات
        Route::get('comments/pending', 'getPendingComments');
        Route::post('comments/{id}/approve', 'approveComment');
    });
});
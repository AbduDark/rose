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
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;
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
    Route::post('/resend-pin',     [AuthController::class, 'resendPin']);
    Route::get('/avatars/{filename}', [UserController::class, 'getAvatar']);
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

    // Subscription routes
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancelSubscription']);
    Route::post('/subscriptions/renew', [SubscriptionController::class, 'renewSubscription']);
    Route::get('/expired-subscriptions', [SubscriptionController::class, 'getExpiredSubscriptions']);
    Route::get('/subscriptions/status/{courseId}', [SubscriptionController::class, 'getSubscriptionStatus']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Comments Routes
    Route::prefix('comments')->group(function () {
        Route::post('/', [CommentController::class, 'store']);                           // إضافة تعليق
        Route::get('/my-comments', [CommentController::class, 'getUserComments']);       // تعليقات المستخدم
        Route::delete('/{id}', [CommentController::class, 'destroy']);                  // حذف تعليق
    });

    // Lesson Comments
    Route::get('lessons/{lessonId}/comments', [CommentController::class, 'getLessonComments']);

    // Favorites
    Route::post('favorite/{course_id}',    [FavoriteController::class, 'add']);
    Route::delete('favorite/{course_id}',  [FavoriteController::class, 'remove']);

    // Lessons
    Route::get('courses/{id}/lessons',     [LessonController::class, 'index']);
    Route::get('lessons/{id}',             [LessonController::class, 'show']);

    // Ratings
    Route::post('ratings',                 [RatingController::class, 'store']);
});

// Admin Routes (Protected)
Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->group(function () {
    // إدارة الكورسات
    Route::apiResource('courses', CourseController::class)->except(['index', 'show']);
    Route::apiResource('lessons', LessonController::class)->except(['index', 'show']);

    // إدارة المستخدمين
    Route::controller(AdminController::class)->group(function () {
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
    });

    // إدارة التعليقات (Admin)
    Route::prefix('comments')->group(function () {
        Route::get('/pending', [CommentController::class, 'getPendingComments']);        // التعليقات المعلقة
        Route::post('/{id}/approve', [CommentController::class, 'approveComment']);      // الموافقة على تعليق
    });

    // Admin subscription management
    Route::get('/subscriptions', [SubscriptionController::class, 'adminIndex']);
    Route::put('/subscriptions/{id}/approve', [SubscriptionController::class, 'approve']);
    Route::put('/subscriptions/{id}/reject', [SubscriptionController::class, 'reject']);

    // Admin notification management
    Route::post('/notifications/send', [NotificationController::class, 'sendNotification']);
    Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
});

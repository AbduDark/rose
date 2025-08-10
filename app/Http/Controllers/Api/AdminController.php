
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\Course;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class AdminController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            if (!$request->user() || $request->user()->role !== 'admin') {
                return $this->errorResponse([
                    'ar' => 'ليس لديك صلاحية للوصول لهذا المحتوى',
                    'en' => 'You do not have permission to access this content'
                ], 403);
            }
            return $next($request);
        });
    }

    // إدارة المستخدمين
    public function getUsers(Request $request)
    {
        try {
            $query = User::query();

            // البحث
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // فلترة حسب الدور
            if ($request->has('role')) {
                $query->where('role', $request->get('role'));
            }

            // فلترة حسب الجنس
            if ($request->has('gender')) {
                $query->where('gender', $request->get('gender'));
            }

            // فلترة حسب حالة التحقق من البريد
            if ($request->has('verified')) {
                if ($request->get('verified') === 'true') {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }

            $users = $query->withCount(['subscriptions', 'favorites', 'payments'])
                          ->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

            return $this->successResponse($users, [
                'ar' => 'تم جلب قائمة المستخدمين بنجاح',
                'en' => 'Users retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Admin get users error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function getUserDetails($id)
    {
        try {
            $user = User::with(['subscriptions.course', 'favorites.course', 'payments.course'])
                       ->withCount(['subscriptions', 'favorites', 'payments'])
                       ->findOrFail($id);

            return $this->successResponse($user, [
                'ar' => 'تم جلب تفاصيل المستخدم بنجاح',
                'en' => 'User details retrieved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'المستخدم غير موجود',
                'en' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin get user details error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone' => 'sometimes|string|max:20|unique:users,phone,' . $id,
                'role' => 'sometimes|in:admin,student',
                'password' => 'sometimes|string|min:8',
                'is_active' => 'sometimes|boolean',
            ], [
                'name.string' => 'الاسم يجب أن يكون نص|Name must be a string',
                'email.email' => 'البريد الإلكتروني غير صحيح|Invalid email format',
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل|Email already exists',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل|Phone number already exists',
                'role.in' => 'الدور يجب أن يكون admin أو student|Role must be admin or student',
                'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف|Password must be at least 8 characters',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $data = $request->only(['name', 'email', 'phone', 'role', 'is_active']);

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            return $this->successResponse($user, [
                'ar' => 'تم تحديث بيانات المستخدم بنجاح',
                'en' => 'User updated successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'المستخدم غير موجود',
                'en' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin update user error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);

            // منع حذف المستخدم الحالي
            if ($user->id === auth()->id()) {
                return $this->errorResponse([
                    'ar' => 'لا يمكنك حذف حسابك الخاص',
                    'en' => 'You cannot delete your own account'
                ], 422);
            }

            $user->delete();

            return $this->successResponse([], [
                'ar' => 'تم حذف المستخدم بنجاح',
                'en' => 'User deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'المستخدم غير موجود',
                'en' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin delete user error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    // إحصائيات الدشبورد
    public function getDashboardStats()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'total_students' => User::where('role', 'student')->count(),
                'total_courses' => Course::count(),
                'active_courses' => Course::where('is_active', true)->count(),
                'total_subscriptions' => Subscription::count(),
                'active_subscriptions' => Subscription::where('is_active', true)->count(),
                'total_payments' => Payment::count(),
                'total_revenue' => Payment::where('status', 'paid')->sum('amount'),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
            ];

            // إحصائيات شهرية
            $monthlyStats = [
                'new_users_this_month' => User::whereMonth('created_at', Carbon::now()->month)
                                              ->whereYear('created_at', Carbon::now()->year)
                                              ->count(),
                'new_subscriptions_this_month' => Subscription::whereMonth('created_at', Carbon::now()->month)
                                                             ->whereYear('created_at', Carbon::now()->year)
                                                             ->count(),
                'revenue_this_month' => Payment::where('status', 'paid')
                                              ->whereMonth('created_at', Carbon::now()->month)
                                              ->whereYear('created_at', Carbon::now()->year)
                                              ->sum('amount'),
            ];

            return $this->successResponse([
                'general_stats' => $stats,
                'monthly_stats' => $monthlyStats
            ], [
                'ar' => 'تم جلب إحصائيات الدشبورد بنجاح',
                'en' => 'Dashboard statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Admin dashboard stats error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    // إدارة الاشتراكات
    public function getSubscriptions(Request $request)
    {
        try {
            $query = Subscription::with(['user', 'course']);

            if ($request->has('status')) {
                if ($request->get('status') === 'active') {
                    $query->where('is_active', true);
                } else {
                    $query->where('is_active', false);
                }
            }

            if ($request->has('approved')) {
                if ($request->get('approved') === 'true') {
                    $query->where('is_approved', true);
                } else {
                    $query->where('is_approved', false);
                }
            }

            $subscriptions = $query->orderBy('created_at', 'desc')
                                  ->paginate($request->get('per_page', 15));

            return $this->successResponse($subscriptions, [
                'ar' => 'تم جلب قائمة الاشتراكات بنجاح',
                'en' => 'Subscriptions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Admin get subscriptions error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function approveSubscription($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            
            $subscription->update([
                'is_approved' => true,
                'is_active' => true,
                'approved_at' => now()
            ]);

            return $this->successResponse($subscription, [
                'ar' => 'تم قبول الاشتراك بنجاح',
                'en' => 'Subscription approved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الاشتراك غير موجود',
                'en' => 'Subscription not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin approve subscription error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function rejectSubscription($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            
            $subscription->update([
                'is_approved' => false,
                'is_active' => false,
                'rejected_at' => now()
            ]);

            return $this->successResponse($subscription, [
                'ar' => 'تم رفض الاشتراك',
                'en' => 'Subscription rejected'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الاشتراك غير موجود',
                'en' => 'Subscription not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin reject subscription error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendPinMail;
use App\Mail\EmailVerificationMail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'required|string|max:20|unique:users',
                'gender' => 'required|in:male,female'
            ], [
                'name.required' => 'الاسم مطلوب|Name is required',
                'name.string' => 'الاسم يجب أن يكون نص|Name must be a string',
                'name.max' => 'الاسم يجب ألا يزيد عن 255 حرف|Name must not exceed 255 characters',
                'email.required' => 'البريد الإلكتروني مطلوب|Email is required',
                'email.email' => 'البريد الإلكتروني غير صحيح|Invalid email format',
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل|Email already exists',
                'password.required' => 'كلمة المرور مطلوبة|Password is required',
                'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف|Password must be at least 8 characters',
                'password.confirmed' => 'تأكيد كلمة المرور غير مطابق|Password confirmation does not match',
                'phone.required' => 'رقم الهاتف مطلوب|Phone number is required',
                'phone.max' => 'رقم الهاتف يجب ألا يزيد عن 20 رقم|Phone number must not exceed 20 digits',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل|Phone number already exists',
                'gender.required' => 'الجنس مطلوب|Gender is required',
                'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى|Gender must be male or female'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $pin = rand(100000, 999999);
            $token = Str::random(60);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'gender' => $request->gender,
                'pin' => $pin,
                'pin_expires_at' => Carbon::now()->addMinutes(10),
                'email_verified_at' => null,
                'role' => 'student'
            ]);

            // Create email verification record
            EmailVerification::create([
                'email' => $user->email,
                'token' => $token,
                'expires_at' => Carbon::now()->addHours(24)
            ]);

            // Send verification email
            try {
                $verificationUrl = url("/api/auth/verify-email?token={$token}");
                Mail::to($user->email)->send(new EmailVerificationMail($verificationUrl));
            } catch (\Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                return $this->errorResponse([
                    'ar' => 'حدث خطأ في إرسال بريد التحقق. يرجى المحاولة لاحقاً.',
                    'en' => 'Failed to send verification email. Please try again later.'
                ], 500);
            }

            return $this->successResponse([
                'user' => $user->only(['id', 'name', 'email', 'phone', 'gender', 'role']),
                'email_verification_required' => true
            ], [
                'ar' => 'تم تسجيل المستخدم بنجاح. يرجى التحقق من بريدك الإلكتروني للتحقق.',
                'en' => 'User registered successfully. Please check your email for verification.'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function login(Request $request)
    {
        try {
            $key = 'login:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->errorResponse([
                    'ar' => 'محاولات دخول كثيرة جداً، حاول مرة أخرى خلال ' . $seconds . ' ثانية',
                    'en' => 'Too many login attempts. Try again in ' . $seconds . ' seconds.'
                ], 429);
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ], [
                'email.required' => 'البريد الإلكتروني مطلوب|Email is required',
                'email.email' => 'البريد الإلكتروني غير صحيح|Invalid email format',
                'password.required' => 'كلمة المرور مطلوبة|Password is required',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, 900);

                Log::channel('security')->warning('Failed login attempt', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return $this->errorResponse([
                    'ar' => 'بيانات الدخول غير صحيحة',
                    'en' => 'Invalid credentials'
                ], 401);
            }

            // التحقق من تأكيد البريد الإلكتروني
            if (!$user->email_verified_at) {
                return $this->errorResponse([
                    'ar' => 'يجب تأكيد البريد الإلكتروني أولاً',
                    'en' => 'Email verification required'
                ], 403, [
                    'email_verification_required' => true,
                    'email' => $user->email
                ]);
            }

            // Check if user is already logged in on another device
            $deviceFingerprint = $this->generateDeviceFingerprint($request);

            // Only check for multiple devices if user has an active session AND a stored device fingerprint
            if ($user->active_session_id &&
                $user->device_fingerprint &&
                $user->device_fingerprint !== $deviceFingerprint &&
                $user->last_login_at) {

                // Revoke all existing tokens
                $user->tokens()->delete();

                Log::channel('security')->warning('Multiple device login attempt', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);

                return $this->errorResponse([
                    'ar' => 'أنت مسجل دخول على جهاز آخر، يرجى تسجيل الخروج من الجهاز الآخر أولاً',
                    'en' => 'You are already logged in on another device. Please logout from that device first.'
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $sessionId = Str::random(40);

            $user->update([
                'active_session_id' => $sessionId,
                'device_fingerprint' => $deviceFingerprint,
                'last_login_at' => now(),
            ]);

            Log::channel('security')->info('User logged in', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            RateLimiter::clear($key);

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'role' => $user->role ?? 'student',
            ];

            if ($user->image) {
                $userData['image'] = $user->image;
                $userData['image_url'] = url('storage/' . $user->image);
            }

            return $this->successResponse([
                'token' => $token,
                'session_id' => $sessionId,
                'user' => $userData
            ], [
                'ar' => 'تم تسجيل الدخول بنجاح',
                'en' => 'Login successful'
            ]);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->errorResponse([
                    'ar' => 'لم يتم العثور على المستخدم',
                    'en' => 'User not found'
                ], 401);
            }

            // Revoke current access token
            $user->currentAccessToken()->delete();

            // Optional: Revoke all tokens
            if ($request->has('logout_all_devices') && $request->logout_all_devices) {
                $user->tokens()->delete();
            }

            // Clear session if exists
            if ($user->active_session_id) {
                $user->update(['active_session_id' => null]);
            }

            return $this->successResponse([], [
                'ar' => 'تم تسجيل الخروج بنجاح',
                'en' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function forceLogout(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => __('messages.auth.invalid_credentials')], 401);
        }

        // Force logout from all devices
        $user->tokens()->delete();
        $user->update([
            'active_session_id' => null,
            'device_fingerprint' => null,
        ]);

        Log::channel('security')->info('Force logout performed', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Successfully logged out from all devices']);
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                    'confirmed'
                ]
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->errorResponse([
                    'ar' => 'كلمة المرور الحالية غير صحيحة',
                    'en' => 'Current password is incorrect'
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return $this->successResponse([], [
                'ar' => 'تم تغيير كلمة المرور بنجاح',
                'en' => 'Password changed successfully'
            ]);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'name.string' => 'الاسم يجب أن يكون نص|Name must be a string',
                'name.max' => 'الاسم يجب ألا يزيد عن 255 حرف|Name must not exceed 255 characters',
                'phone.string' => 'رقم الهاتف يجب أن يكون نص|Phone must be a string',
                'phone.max' => 'رقم الهاتف يجب ألا يزيد عن 20 رقم|Phone number must not exceed 20 digits',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل|Phone number already exists',
                'image.image' => 'الملف المرفوع يجب أن يكون صورة|Uploaded file must be an image',
                'image.mimes' => 'نوع الصورة يجب أن يكون jpeg, png, jpg, أو gif|Image must be jpeg, png, jpg, or gif',
                'image.max' => 'حجم الصورة يجب ألا يزيد عن 2 ميجابايت|Image size must not exceed 2MB'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            // Update image if provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
                $user->image = $request->file('image')->store('profiles', 'public');
            }

            // Update name if provided
            if ($request->filled('name')) {
                $user->name = $request->name;
            }

            // Update phone if provided
            if ($request->filled('phone')) {
                $user->phone = $request->phone;
            }

            $user->save();

            // إعادة تحميل البيانات من قاعدة البيانات
            $user->refresh();

            // إضافة URL كامل للصورة
            $userData = $user->only(['id', 'name', 'email', 'phone', 'gender', 'image', 'role']);
            if ($user->image) {
                $userData['image_url'] = url('storage/' . $user->image);
            }

            return $this->successResponse([
                'user' => $userData
            ], [
                'ar' => 'تم تحديث الملف الشخصي بنجاح',
                'en' => 'Profile updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $token = Str::random(60);

        EmailVerification::updateOrCreate(
            ['email' => $request->email],
            ['token' => $token, 'expires_at' => now()->addMinutes(60)]
        );

        $resetUrl = url("/api/auth/reset-password?token={$token}");
        Mail::to($request->email)->send(new EmailVerificationMail($resetUrl));

        Log::channel('security')->info('Password reset requested', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Password reset link has been sent to your email']);
    }

    private function generateDeviceFingerprint(Request $request)
    {
        $data = [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'accept_language' => $request->header('Accept-Language'),
            'accept_encoding' => $request->header('Accept-Encoding'),
        ];

        return hash('sha256', json_encode($data));
    }

    public function verifyEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $token = $request->query('token');

            if (!$token) {
                throw new \Exception('رابط التحقق غير صالح');
            }

            $verification = EmailVerification::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if (!$verification) {
                throw new \Exception('رابط التحقق غير صالح أو منتهي الصلاحية');
            }

            $user = User::where('email', $verification->email)->first();

            if (!$user) {
                throw new \Exception('المستخدم غير موجود');
            }

            // تحديث حالة التحقق من البريد
            $user->email_verified_at = now();
            $user->save();

            // حذف سجل التحقق بعد نجاح التحقق
            $verification->delete();

            DB::commit();

            // تسجيل العملية
            Log::channel('security')->info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // إعادة توجيه إلى صفحة النجاح
            return redirect('/email-verified?success=true');

        } catch (\Exception $e) {
            DB::rollback();

            Log::channel('security')->error('Error verifying email', [
                'error' => $e->getMessage(),
                'email' => $verification->email ?? 'unknown',
                'token' => $token
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }

            return redirect('/email-verified?error=' . urlencode($e->getMessage()));
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character.'
        ]);

        $verification = EmailVerification::where('token', $request->token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired token'], 422);
        }

        $user = User::where('email', $verification->email)->first();
        $user->password = Hash::make($request->password);

        // Force logout from all devices
        $user->tokens()->delete();
        $user->active_session_id = null;
        $user->device_fingerprint = null;
        $user->save();

        $verification->delete();

        Log::channel('security')->info('Password reset completed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Password has been reset successfully']);
    }

    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified'], 422);
        }

        // التحقق من Rate Limiting
        $key = 'resend_verification:' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many attempts. Try again in ' . $seconds . ' seconds.'
            ], 429);
        }

        $token = Str::random(60);
        EmailVerification::updateOrCreate(
            ['email' => $request->email],
            ['token' => $token, 'expires_at' => now()->addMinutes(60)]
        );

        $verificationUrl = url("/api/auth/verify-email?token={$token}");
        Mail::to($request->email)->send(new EmailVerificationMail($verificationUrl));

        RateLimiter::hit($key, 60);

        Log::channel('security')->info('Email verification link resent', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Verification link has been resent. Please check your email.']);
    }

    protected function validationErrorResponse(ValidationException $exception)
    {
        $errors = $exception->errors();

        $formattedErrors = [];
        foreach ($errors as $field => $messages) {
            $formattedErrors[$field] = [
                'ar' => $messages[0] ?? 'خطأ في التحقق',
                'en' => $messages[0] ?? 'Validation error',
            ];
            if (count($messages) > 1) {
                $formattedErrors[$field]['en'] .= ' (and ' . (count($messages) - 1) . ' more)';
                $formattedErrors[$field]['ar'] .= ' (و ' . (count($messages) - 1) . ' المزيد)';
            }
        }

        return response()->json([
            'success' => false,
            'message' => [
                'ar' => 'فشل التحقق من البيانات',
                'en' => 'The given data failed to validate',
            ],
            'errors' => $formattedErrors,
        ], 422);
    }

    protected function successResponse(array $data = [], array $messages = ['en' => 'Success'], int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $messages,
            'data' => $data,
        ], $status);
    }

    protected function serverErrorResponse(string $message = 'An unexpected error occurred on the server.')
    {
        return response()->json([
            'success' => false,
            'message' => [
                'ar' => 'حدث خطأ غير متوقع في الخادم.',
                'en' => $message,
            ],
        ], 500);
    }

    protected function errorResponse(array $messages, int $status = 400, array $data = [])
    {
        return response()->json([
            'success' => false,
            'message' => $messages,
            'data' => $data
        ], $status);
    }
}
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
use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use App\Traits\ApiResponseTrait;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use ApiResponseTrait;

    // Constants for rate limiting
    const MAX_LOGIN_ATTEMPTS = 10;
    const LOGIN_DECAY_SECONDS = 900; // 15 minutes
    const MAX_PASSWORD_RESET_ATTEMPTS = 3;
    const PASSWORD_RESET_DECAY_SECONDS = 3600; // 1 hour
    const MAX_VERIFICATION_RESEND_ATTEMPTS = 3;
    const VERIFICATION_RESEND_DECAY_SECONDS = 3600; // 1 hour

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]+$/',
                'phone' => 'required|string|max:20|unique:users',
                'gender' => 'required|in:male,female'
            ], [
                'password.regex' => 'كلمة المرور يجب أن تحتوي على حروف وأرقام|Password must contain letters and numbers'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'gender' => $request->gender,
                'role' => 'student',
                'email_verified_at' => null
            ]);

            $token = Str::random(60);
            $verification = EmailVerification::create([
                'email' => $user->email,
                'token' => $token,
                'expires_at' => Carbon::now()->addHours(24)
            ]);

            try {
                $verificationUrl = url("/api/auth/verify-email?token={$token}");
                Mail::to($user->email)->send(new EmailVerificationMail($verificationUrl));

                DB::commit();

                return $this->successResponse(
                    [
                        'user' => $user->only(['id', 'name', 'email', 'phone', 'gender', 'role']),
                        'email_verification_required' => true
                    ],
                    [
                        'ar' => 'تم التسجيل بنجاح. يرجى التحقق من بريدك الإلكتروني',
                        'en' => 'Registration successful. Please verify your email'
                    ],
                    Response::HTTP_CREATED
                );

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Email sending failed: ' . $e->getMessage());

                return $this->errorResponse(
                    [
                        'ar' => 'حدث خطأ في إرسال بريد التحقق',
                        'en' => 'Failed to send verification email'
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    ['retry_possible' => true]
                );
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed: ' . $e->getMessage());

            return $this->serverErrorResponse(
                'Failed to complete registration. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Authenticate user and generate token
     */
    public function login(Request $request)
    {
        try {
            $key = 'login:' . $request->ip();

            if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->errorResponse(
                    [
                        'ar' => 'محاولات دخول كثيرة. حاول بعد ' . $seconds . ' ثانية',
                        'en' => 'Too many attempts. Try again in ' . $seconds . ' seconds'
                    ],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['retry_after' => $seconds]
                );
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, self::LOGIN_DECAY_SECONDS);

                Log::channel('security')->warning('Failed login attempt', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);

                return $this->errorResponse(
                    [
                        'ar' => 'بيانات الدخول غير صحيحة',
                        'en' => 'Invalid credentials'
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            if (!$user->email_verified_at) {
                return $this->errorResponse(
                    [
                        'ar' => 'يجب تأكيد البريد الإلكتروني أولاً',
                        'en' => 'Email verification required'
                    ],
                    Response::HTTP_FORBIDDEN,
                    [
                        'email_verification_required' => true,
                        'email' => $user->email
                    ]
                );
            }

            $deviceFingerprint = $this->generateDeviceFingerprint($request);

            if ($user->active_session_id && $user->device_fingerprint !== $deviceFingerprint) {
                $user->tokens()->delete();

                Log::channel('security')->warning('Multiple device login attempt', [
                    'user_id' => $user->id,
                    'ip' => $request->ip()
                ]);

                return $this->errorResponse(
                    [
                        'ar' => 'مسجل دخول بالفعل على جهاز آخر',
                        'en' => 'Already logged in on another device'
                    ],
                    Response::HTTP_FORBIDDEN
                );
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $sessionId = Str::random(40);

            $user->update([
                'active_session_id' => $sessionId,
                'device_fingerprint' => $deviceFingerprint,
                'last_login_at' => now(),
            ]);

            RateLimiter::clear($key);

            $userData = $this->prepareUserData($user);

            Log::channel('security')->info('User logged in', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return $this->successResponse(
                [
                    'token' => $token,
                    'session_id' => $sessionId,
                    'user' => $userData
                ],
                [
                    'ar' => 'تم تسجيل الدخول بنجاح',
                    'en' => 'Login successful'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->errorResponse(
                    [
                        'ar' => 'غير مصرح',
                        'en' => 'Unauthorized'
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            return $this->successResponse(
                ['user' => $this->prepareUserData($user)],
                [
                    'ar' => 'تم جلب بيانات الملف الشخصي',
                    'en' => 'Profile data retrieved'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Profile error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->errorResponse(
                    [
                        'ar' => 'يجب تسجيل الدخول أولاً',
                        'en' => 'You must be logged in'
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Revoke current token
            if (method_exists($user, 'currentAccessToken')) {
                $user->currentAccessToken()->delete();
            } else {
                $request->user()->token()->revoke();
            }

            // Clear session
            $user->update(['active_session_id' => null]);

            Log::info('User logged out', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return $this->successResponse(
                [],
                [
                    'ar' => 'تم تسجيل الخروج بنجاح',
                    'en' => 'Logged out successfully'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Force logout from all devices
     */
    public function forceLogout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->errorResponse(
                    [
                        'ar' => 'يجب تسجيل الدخول أولاً',
                        'en' => 'You must be logged in'
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            if ($user->email !== $request->email) {
                return $this->errorResponse(
                    [
                        'ar' => 'غير مصرح بهذا الإجراء',
                        'en' => 'Unauthorized action'
                    ],
                    Response::HTTP_FORBIDDEN
                );
            }

            // Revoke all tokens
            $user->tokens()->delete();

            // Clear all sessions
            $user->update([
                'active_session_id' => null,
                'device_fingerprint' => null,
            ]);

            Log::channel('security')->info('Force logout performed', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return $this->successResponse(
                [],
                [
                    'ar' => 'تم تسجيل الخروج من جميع الأجهزة',
                    'en' => 'Logged out from all devices'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Force logout error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                    'confirmed'
                ]
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->errorResponse(
                    [
                        'ar' => 'كلمة المرور الحالية غير صحيحة',
                        'en' => 'Current password is incorrect'
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $user->update(['password' => Hash::make($request->new_password)]);

            // Logout from all devices after password change
            $user->tokens()->delete();
            $user->update(['active_session_id' => null]);

            Log::info('Password changed', ['user_id' => $user->id]);

            return $this->successResponse(
                [],
                [
                    'ar' => 'تم تغيير كلمة المرور بنجاح',
                    'en' => 'Password changed successfully'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Password change error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                try {
                    $this->handleProfileImageUpload($user, $request->file('image'));
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Image upload failed: ' . $e->getMessage());

                    return $this->errorResponse(
                        [
                            'ar' => 'فشل في رفع الصورة',
                            'en' => 'Failed to upload image'
                        ],
                        Response::HTTP_INTERNAL_SERVER_ERROR
                    );
                }
            }

            // Update other fields
            $user->fill($request->only(['name', 'phone']));

            if (!$user->save()) {
                DB::rollBack();
                return $this->errorResponse(
                    [
                        'ar' => 'فشل في حفظ التغييرات',
                        'en' => 'Failed to save changes'
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            DB::commit();

            return $this->successResponse(
                ['user' => $this->prepareUserData($user)],
                [
                    'ar' => 'تم تحديث الملف الشخصي',
                    'en' => 'Profile updated'
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Handle forgot password request
     */
    public function forgotPassword(Request $request)
    {
        try {
            $key = 'password_reset:' . $request->email;

            if (RateLimiter::tooManyAttempts($key, self::MAX_PASSWORD_RESET_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->errorResponse(
                    [
                        'ar' => 'محاولات كثيرة. حاول بعد ' . ceil($seconds/60) . ' دقيقة',
                        'en' => 'Too many attempts. Try again in ' . ceil($seconds/60) . ' minutes'
                    ],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['retry_after' => $seconds]
                );
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $user = User::where('email', $request->email)->first();
            $token = Str::random(64);

            EmailVerification::updateOrCreate(
                ['email' => $request->email],
                [
                    'token' => $token,
                    'expires_at' => now()->addMinutes(60)
                ]
            );

            try {
                $resetUrl = url("/api/auth/reset-password?token={$token}&email=" . urlencode($request->email));
                Mail::to($request->email)->send(new PasswordResetMail($resetUrl, $user));

                RateLimiter::hit($key, self::PASSWORD_RESET_DECAY_SECONDS);

                Log::channel('security')->info('Password reset requested', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);

                return $this->successResponse(
                    [],
                    [
                        'ar' => 'تم إرسال رابط إعادة التعيين',
                        'en' => 'Reset link has been sent'
                    ]
                );

            } catch (\Exception $e) {
                Log::error('Failed to send reset email: ' . $e->getMessage());
                return $this->errorResponse(
                    [
                        'ar' => 'فشل في إرسال البريد',
                        'en' => 'Failed to send email'
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    ['retry_possible' => true]
                );
            }

        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request)
    {
        DB::beginTransaction();

        try {
            $token = $request->query('token');

            if (!$token) {
                throw new \Exception('Missing verification token');
            }

            $verification = EmailVerification::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if (!$verification) {
                throw new \Exception('Invalid or expired token');
            }

            $user = User::where('email', $verification->email)->first();

            if (!$user) {
                throw new \Exception('User not found');
            }

            $user->email_verified_at = now();
            $user->save();
            $verification->delete();

            DB::commit();

            Log::info('Email verified', ['user_id' => $user->id]);

            return redirect('/email-verified?success=true');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Email verification error: ' . $e->getMessage());

            return redirect('/email-verified?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ]
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $verification = EmailVerification::where('token', $request->token)
                ->where('email', $request->email)
                ->where('expires_at', '>', now())
                ->first();

            if (!$verification) {
                DB::rollBack();
                return $this->errorResponse(
                    [
                        'ar' => 'رابط إعادة التعيين غير صالح',
                        'en' => 'Invalid reset link'
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                DB::rollBack();
                return $this->errorResponse(
                    [
                        'ar' => 'المستخدم غير موجود',
                        'en' => 'User not found'
                    ],
                    Response::HTTP_NOT_FOUND
                );
            }

            $user->password = Hash::make($request->password);
            $user->tokens()->delete();
            $user->active_session_id = null;
            $user->device_fingerprint = null;
            $user->save();
            $verification->delete();

            DB::commit();

            Log::channel('security')->info('Password reset completed', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return $this->successResponse(
                [],
                [
                    'ar' => 'تم إعادة تعيين كلمة المرور',
                    'en' => 'Password has been reset'
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Password reset error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        try {
            $key = 'resend_verification:' . $request->email;

            if (RateLimiter::tooManyAttempts($key, self::MAX_VERIFICATION_RESEND_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->errorResponse(
                    [
                        'ar' => 'محاولات كثيرة. حاول بعد ' . ceil($seconds/60) . ' دقيقة',
                        'en' => 'Too many attempts. Try again in ' . ceil($seconds/60) . ' minutes'
                    ],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['retry_after' => $seconds]
                );
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $user = User::where('email', $request->email)->first();

            if ($user->email_verified_at) {
                return $this->errorResponse(
                    [
                        'ar' => 'البريد مفعل بالفعل',
                        'en' => 'Email already verified'
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $token = Str::random(60);
            EmailVerification::updateOrCreate(
                ['email' => $request->email],
                [
                    'token' => $token,
                    'expires_at' => now()->addHours(24)
                ]
            );

            try {
                $verificationUrl = url("/api/auth/verify-email?token={$token}");
                Mail::to($request->email)->send(new EmailVerificationMail($verificationUrl));

                RateLimiter::hit($key, self::VERIFICATION_RESEND_DECAY_SECONDS);

                Log::info('Verification email resent', ['email' => $request->email]);

                return $this->successResponse(
                    [],
                    [
                        'ar' => 'تم إعادة إرسال بريد التحقق',
                        'en' => 'Verification email resent'
                    ]
                );

            } catch (\Exception $e) {
                Log::error('Failed to resend verification: ' . $e->getMessage());
                return $this->errorResponse(
                    [
                        'ar' => 'فشل في إرسال البريد',
                        'en' => 'Failed to send email'
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    ['retry_possible' => true]
                );
            }

        } catch (\Exception $e) {
            Log::error('Resend verification error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Generate device fingerprint
     */
    private function generateDeviceFingerprint(Request $request): string
    {
        $data = [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'accept_language' => $request->header('Accept-Language'),
            'accept_encoding' => $request->header('Accept-Encoding'),
        ];

        return hash('sha256', json_encode($data));
    }

    /**
     * Prepare user data for response
     */
    private function prepareUserData(User $user): array
{
    $data = [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'gender' => $user->gender,
        'role' => $user->role ?? 'student',
    ];

    if ($user->image) {
        $data['image'] = $user->image;
       $data['image_url'] = asset('storage/' . $user->image);
    }

    return $data;
}

protected function serverErrorResponse(string $message = 'An unexpected error occurred on the server.', int $status = 500)
{
    return response()->json([
        'success' => false,
        'message' => [
            'ar' => 'حدث خطأ غير متوقع في الخادم.',
            'en' => $message,
        ],
    ], $status);
}

    /**
     * Handle profile image upload
     */
    private function handleProfileImageUpload(User $user, $image): void
    {
        if (!Storage::disk('public')->exists('profiles')) {
            Storage::disk('public')->makeDirectory('profiles');
        }

        // Delete old image if exists
        if ($user->image && Storage::disk('public')->exists($user->image)) {
            Storage::disk('public')->delete($user->image);
        }

        $imagePath = $image->store('profiles', 'public');
        $user->image = $imagePath;
        $user->save();
    }
}

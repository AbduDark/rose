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

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $key = 'register:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => __('messages.general.error'),
                'error' => 'Too many registration attempts. Try again later.'
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'gender'   => 'required|in:male,female',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character.'
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key, 300);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deviceFingerprint = $this->generateDeviceFingerprint($request);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'gender'   => $request->gender,
            'device_fingerprint' => $deviceFingerprint,
        ]);

        // إرسال رابط التحقق من الإيميل
        $token = Str::random(60);

        EmailVerification::create([
            'email' => $request->email,
            'token' => $token,
            'expires_at' => now()->addMinutes(60), // ساعة واحدة
        ]);

        $verificationUrl = url("/api/auth/verify-email?token={$token}");
        Mail::to($request->email)->send(new EmailVerificationMail($verificationUrl));

        // The token is created here, but the user is not logged in until email is verified.
        // This is handled by the `email_verified_at` check in the login method.

        Log::channel('security')->info('User registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        RateLimiter::clear($key);

        return response()->json([
            'message' => __('messages.auth.registered_successfully'),
            'email_verification_required' => true
        ], 201);
    }

    public function login(Request $request)
    {
        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => __('messages.general.error'),
                'error' => 'Too many login attempts. Try again in ' . $seconds . ' seconds.'
            ], 429);
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 900);

            Log::channel('security')->warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => __('messages.auth.invalid_credentials')], 401);
        }

        // التحقق من تأكيد البريد الإلكتروني
        if (!$user->email_verified_at) {
            return response()->json([
                'message' => __('messages.auth.email_not_verified'),
                'email_verification_required' => true,
                'email' => $user->email
            ], 403);
        }

        // Check if user is already logged in on another device
        $deviceFingerprint = $this->generateDeviceFingerprint($request);

        // Only check for multiple devices if user has an active session AND a stored device fingerprint
        // This prevents issues for new users who just registered but haven't logged in before
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

            return response()->json([
                'message' => __('messages.auth.already_logged_in_another_device'),
                'error' => 'You are already logged in on another device. Please logout from that device first.'
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

        return response()->json([
            'token' => $token,
            'session_id' => $sessionId,
            'message' => __('messages.auth.login_done'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'student',
            ]
        ]);
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
                return response()->json([
                    'message' => 'User not authenticated'
                ], 401);
            }

            // تسجيل محاولة تسجيل الخروج
            Log::channel('security')->info('User logout attempt', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // حذف التوكن الحالي
            if ($request->bearerToken()) {
                $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();
            }

            // تحديث بيانات الجلسة
            $user->update([
                'active_session_id' => null,
                'device_fingerprint' => null,
                'last_logout_at' => now()
            ]);

            Log::channel('security')->info('User logged out successfully', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.auth.logged_out_successfully')
            ]);
        } catch (\Exception $e) {
            Log::channel('security')->error('Error during logout', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الخروج'
            ], 500);
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
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character.'
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            Log::channel('security')->warning('Failed password change attempt', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => __('messages.auth.current_password_incorrect')], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // Force logout from all other sessions
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        Log::channel('security')->info('Password changed', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => __('messages.auth.password_changed_successfully')]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('profiles', 'public');
            $user->image = $path;
        }

        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        $user->save();

        return response()->json(['message' => __('messages.auth.profile_updated_successfully'), 'user' => $user]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        // For password reset, we still use PIN for now, as per original logic.
        // The request to change this to a link for password reset was not explicit,
        // but the email verification was changed to a link.
        $pin = rand(100000, 999999);

        EmailVerification::updateOrCreate(
            ['email' => $request->email],
            ['pin' => $pin, 'expires_at' => now()->addMinutes(5)]
        );

        Mail::to($request->email)->send(new SendPinMail($pin));

        Log::channel('security')->info('Password reset requested', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => __('messages.auth.verification_pin_sent')]);
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
            'email'    => 'required|email|exists:users,email',
            'pin'      => 'required|string', // Still uses PIN for password reset
            'password' => 'required|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character.'
        ]);

        $verification = EmailVerification::where('email', $request->email)
            ->where('pin', $request->pin)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => __('messages.auth.invalid_or_expired_pin')], 422);
        }

        $user = User::where('email', $request->email)->first();
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

        return response()->json(['message' => __('messages.auth.password_reset_successfully')]);
    }

    public function resendPin(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json(['message' => __('messages.auth.email_already_verified')], 422);
        }

        // التحقق من Rate Limiting
        $key = 'resend_pin:' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => __('messages.general.error'),
                'error' => 'Too many attempts. Try again in ' . $seconds . ' seconds.'
            ], 429);
        }

        // For resending verification link, we need to generate a new token.
        $token = Str::random(60);
        EmailVerification::updateOrCreate(
            ['email' => $request->email],
            ['token' => $token, 'expires_at' => now()->addMinutes(60)] // extend expiry
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
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit; // تم التعديل هنا

class AuthController extends Controller
{
    // Unified password regex pattern
    private const PASSWORD_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';

    public function __construct()
    {
        // Improved RateLimiter with combined IP and email key
        RateLimiter::for('login', function (Request $request) {
            $key = $request->ip() . '|' . Str::lower($request->email);
            return Limit::perMinute(5)->by($key);
        });
    }

    /**
     * تسجيل مستخدم جديد
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'regex:'.self::PASSWORD_REGEX],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Log::channel('security')->info('User registered', [
            'id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return response()->json(['message' => 'تم تسجيل المستخدم بنجاح']);
    }

    /**
     * تسجيل الدخول
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            Log::channel('security')->warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        $user = $request->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        Log::channel('security')->info('User logged in', [
            'id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return response()->json(['token' => $token]);
    }

    /**
     * تسجيل الخروج
     */
        public function logout(Request $request)
    {
        $user = $request->user();

        // الطريقة الصحيحة للتعامل مع Sanctum
        if (method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()->delete();
        } else {
            // Fallback للتوافق مع أنظمة المصادقة الأخرى
            Auth::guard('web')->logout();
        }

        Log::channel('security')->info('User logged out', [
            'id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }


    /**
     * تحديث البروفايل
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'image' => ['sometimes', 'image', 'max:2048'],
        ]);

        if ($validator->fails()) {
            Log::channel('security')->debug('Profile update validation failed', [
                'errors' => $validator->errors()->toArray(),
                'user_id' => $user->id
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->hasFile('image')) {
            try {
                $imageName = Str::random(40) . '.' . $request->image->extension();
                $imagePath = $request->image->storeAs('profiles', $imageName, 'public');
                $user->image = 'storage/' . $imagePath;

                Log::channel('security')->debug('Image uploaded successfully', [
                    'user_id' => $user->id,
                    'path' => $user->image
                ]);
            } catch (\Exception $e) {
                Log::channel('security')->error('Image upload failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                return response()->json(['message' => 'فشل رفع الصورة'], 500);
            }
        }

        $user->save();

        Log::channel('security')->info('User updated profile', [
            'id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return response()->json(['message' => 'تم تحديث الملف الشخصي بنجاح', 'user' => $user]);
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required'],
            'password'         => ['required', 'string', 'min:8', 'regex:'.self::PASSWORD_REGEX, 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Hash::check($request->current_password, $request->user()->password)) {
            Log::channel('security')->warning('Failed password change attempt', [
                'user_id' => $request->user()->id,
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة'], 403);
        }

        $request->user()->update(['password' => Hash::make($request->password)]);

        Log::channel('security')->info('User changed password', [
            'id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip()
        ]);

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح']);
    }

    /**
     * إعادة تعيين كلمة المرور - إرسال الرابط
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            Log::channel('security')->info('Password reset link sent', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'تم إرسال رابط إعادة تعيين كلمة المرور']);
        }

        Log::channel('security')->error('Failed to send password reset link', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);
        return response()->json(['message' => 'تعذر إرسال رابط إعادة التعيين'], 500);
    }

    /**
     * إعادة تعيين كلمة المرور - حفظ الجديدة
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'regex:'.self::PASSWORD_REGEX, 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                Log::channel('security')->info('User reset password', [
                    'id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip()
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح']);
        }

        Log::channel('security')->error('Password reset failed', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);
        return response()->json(['message' => 'فشل إعادة تعيين كلمة المرور'], 500);
    }
}

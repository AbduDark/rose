<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من أن المستخدم مسجل دخول
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        // التحقق من أن المستخدم لا يستخدم أكثر من جلسة واحدة
        $user = Auth::user();
        $currentSession = session()->getId();

        // إذا كان هناك جلسة مختلفة مخزنة للمستخدم
        if ($user->current_session && $user->current_session !== $currentSession) {
            // إنهاء الجلسة الحالية
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return response()->json([
                'success' => false,
                'message' => 'تم تسجيل دخولك من جهاز آخر. يرجى تسجيل الدخول مرة أخرى.',
                'error_code' => 'MULTIPLE_SESSIONS'
            ], 403);
        }

        // تحديث معرف الجلسة للمستخدم
        if ($user->current_session !== $currentSession) {
            $user->current_session = $currentSession;
            $user->save();
        }

        return $next($request);
    }
}

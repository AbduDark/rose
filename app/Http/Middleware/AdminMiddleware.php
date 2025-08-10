<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json(['message' => __('messages.general.unauthorized')], 403);
        }

        return $next($request);
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class AdminMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return $this->errorResponse([
                'ar' => 'يجب تسجيل الدخول',
                'en' => 'Authentication required'
            ], 401);
        }

        if ($request->user()->role !== 'admin') {
            return $this->errorResponse([
                'ar' => 'ليس لديك صلاحية الوصول لهذا المحتوى',
                'en' => 'You do not have permission to access this content'
            ], 403);
        }

        return $next($request);
    }
}

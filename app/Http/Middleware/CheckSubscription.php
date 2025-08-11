
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class CheckSubscription
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // Allow admins to access everything
        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        // Get course_id from route parameters
        $courseId = $request->route('course') ?? $request->route('id');
        
        if (!$courseId || !$user) {
            return $this->errorResponse([
                'ar' => 'غير مصرح لك بالوصول',
                'en' => 'Unauthorized access'
            ], 401);
        }

        // Check if user is subscribed to the course
        if (!$user->isSubscribedTo($courseId)) {
            return $this->errorResponse([
                'ar' => 'يجب أن تكون مشتركاً في هذا الكورس للوصول إلى محتواه',
                'en' => 'You must be subscribed to this course to access its content'
            ], 403);
        }

        return $next($request);
    }
}

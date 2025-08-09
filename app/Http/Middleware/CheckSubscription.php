<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Subscription;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $courseId = $request->route('course') ?? $request->course_id;

        if (!$courseId) {
            return response()->json(['message' => 'Course ID is required'], 400);
        }

        $hasSubscription = Subscription::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->exists();

        if (!$hasSubscription) {
            return response()->json(['message' => 'You must subscribe to this course first'], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GenderContentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $request->has('gender_filter')) {
            $genderFilter = $request->gender_filter;

            if ($genderFilter !== 'all' && $user->gender !== $genderFilter) {
                return response()->json([
                    'message' => 'This content is not available for your gender'
                ], 403);
            }
        }

        return $next($request);
    }
}

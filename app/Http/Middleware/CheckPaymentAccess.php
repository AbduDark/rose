<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Payment;

class CheckPaymentAccess
{
    public function handle(Request $request, Closure $next)
    {
        $paymentId = $request->route('id');
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Admin can access all payments
        if ($user->role === 'admin') {
            return $next($request);
        }

        // User can only access their own payments
        $payment = Payment::find($paymentId);

        if (!$payment || $payment->user_id !== $user->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        return $next($request);
    }
}

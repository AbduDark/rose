<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponseTrait;
class CheckSubscriptionExpiry
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Check for expired subscriptions
        $expiredSubscriptions = $user->subscriptions()
            ->where('status', 'approved')
            ->where('expires_at', '<', now())
            ->where('is_active', true)
            ->get();

        if ($expiredSubscriptions->count() > 0) {
            // Deactivate expired subscriptions
            foreach ($expiredSubscriptions as $subscription) {
                $subscription->update(['is_active' => false]);
            }

            // Check if this is an API request to a course or lesson
            if ($request->is('api/courses/*') || $request->is('api/lessons/*')) {
                return $this->errorResponse([
                    'ar' => 'انتهت صلاحية اشتراكك. يرجى تجديد الاشتراك للمتابعة',
                    'en' => 'Your subscription has expired. Please renew your subscription to continue'
                ], 403, [
                    'subscription_expired' => true,
                    'expired_subscriptions' => $expiredSubscriptions->map(function($sub) {
                        return [
                            'id' => $sub->id,
                            'course_id' => $sub->course_id,
                            'course_title' => $sub->course->title ?? null,
                            'expires_at' => $sub->expires_at
                        ];
                    })
                ]);
            }
        }

        // Check for subscriptions expiring soon (within 3 days)
        $expiringSoon = $user->subscriptions()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(3))
            ->get();

        if ($expiringSoon->count() > 0 && ($request->is('api/courses/*') || $request->is('api/lessons/*'))) {
            // Add warning to response headers
            $request->attributes->set('subscription_warning', [
                'ar' => 'تنتهي صلاحية اشتراكك قريباً. يرجى التجديد قبل انتهاء الصلاحية',
                'en' => 'Your subscription expires soon. Please renew before expiry'
            ]);

            $request->attributes->set('expiring_subscriptions', $expiringSoon->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'course_id' => $sub->course_id,
                    'course_title' => $sub->course->title ?? null,
                    'expires_at' => $sub->expires_at,
                    'days_remaining' => $sub->getDaysRemaining()
                ];
            }));
        }

        return $next($request);
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CheckSubscriptionExpiry
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Check for expired subscriptions and update status
            $expiredSubscriptions = $user->subscriptions()
                ->where('status', 'active')
                ->where('expires_at', '<', Carbon::now())
                ->get();
            
            foreach ($expiredSubscriptions as $subscription) {
                $subscription->update(['status' => 'expired']);
            }
        }
        
        return $next($request);
    }
}
```
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Subscription;
use Carbon\Carbon;

class CheckSubscriptionExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found'], 403);
        }

        if ($subscription->expires_at && Carbon::parse($subscription->expires_at)->isPast()) {
            // Update subscription status to expired
            $subscription->update(['status' => 'expired']);
            
            return response()->json([
                'error' => 'Subscription has expired',
                'expired_at' => $subscription->expires_at
            ], 403);
        }

        return $next($request);
    }
}

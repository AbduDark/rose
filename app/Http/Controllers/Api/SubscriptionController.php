<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Course;
use App\Models\Payment;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->with('course')
            ->where('is_active', true)
            ->where('is_approved', true)
            ->where('expires_at', '>', now())
            ->get();

        return response()->json($subscriptions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = $request->user();
        $courseId = $request->course_id;

        // Check if already subscribed
        $existingSubscription = Subscription::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'message' => 'أنت مشترك بالفعل في هذه الدورة'
            ], 400);
        }

        // Create pending subscription (requires payment approval)
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'subscribed_at' => now(),
            'expires_at' => now()->addDays(30),
            'is_active' => false, // Will be activated after payment approval
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'تم إنشاء طلب الاشتراك. يرجى الدفع وانتظار موافقة الإدارة',
            'subscription' => $subscription
        ], 201);
    }

    public function destroy($courseId, Request $request)
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }

        $subscription->update(['is_active' => false]);

        return response()->json(['message' => 'تم إلغاء الاشتراك بنجاح']);
    }

    // Admin functions
    public function approveSubscription(Request $request, $subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);

        $subscription->update([
            'is_approved' => true,
            'is_active' => true,
            'approved_at' => now(),
            'admin_notes' => $request->admin_notes,
        ]);

        return response()->json([
            'message' => 'تم الموافقة على الاشتراك',
            'subscription' => $subscription
        ]);
    }

    public function rejectSubscription(Request $request, $subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);

        $subscription->update([
            'is_approved' => false,
            'is_active' => false,
            'admin_notes' => $request->admin_notes,
        ]);

        return response()->json([
            'message' => 'تم رفض الاشتراك',
            'subscription' => $subscription
        ]);
    }

    public function pendingSubscriptions()
    {
        $subscriptions = Subscription::with(['user', 'course'])
            ->where('is_approved', false)
            ->where('is_active', false)
            ->get();

        return response()->json($subscriptions);
    }

    public function renewSubscription(Request $request, $courseId)
    {
        $user = $request->user();

        $subscription = Subscription::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('is_approved', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }

        // Extend subscription by 30 days
        $subscription->update([
            'expires_at' => $subscription->expires_at->addDays(30),
            'is_active' => false, // Requires new payment approval
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'تم تجديد الاشتراك. يرجى الدفع وانتظار موافقة الإدارة',
            'subscription' => $subscription
        ]);
    }
}

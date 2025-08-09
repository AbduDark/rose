<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Course;
use App\Models\Subscription;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'payment_method' => 'required|in:vodafone,orange,etisalat,we',
            'phone_number' => 'required|string|regex:/^01[0-9]{9}$/',
            'transaction_id' => 'nullable|string',
        ]);

        $course = Course::findOrFail($request->course_id);
        $user = $request->user();

        // Check if already subscribed
        if ($user->isSubscribedTo($request->course_id)) {
            return response()->json(['message' => 'Already subscribed to this course'], 400);
        }

        // Check if payment already exists
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('course_id', $request->course_id)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            return response()->json(['message' => 'Payment already pending for this course'], 400);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'course_id' => $request->course_id,
            'amount' => $course->price,
            'payment_method' => $request->payment_method,
            'phone_number' => $request->phone_number,
            'transaction_id' => $request->transaction_id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Payment submitted successfully. It will be reviewed by admin.',
            'payment' => $payment
        ], 201);
    }

    public function pending()
    {
        $payments = Payment::with(['user:id,name,email', 'course:id,title'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    public function accept(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'Payment already processed'], 400);
        }

        $payment->update([
            'status' => 'approved',
            'approved_at' => now(),
            'admin_notes' => $request->admin_notes,
        ]);

        // Create subscription
        Subscription::create([
            'user_id' => $payment->user_id,
            'course_id' => $payment->course_id,
            'subscribed_at' => now(),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Payment approved and subscription created',
            'payment' => $payment
        ]);
    }

    public function reject(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'Payment already processed'], 400);
        }

        $payment->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        return response()->json([
            'message' => 'Payment rejected',
            'payment' => $payment
        ]);
    }
}

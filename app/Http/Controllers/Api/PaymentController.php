<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Course;
use App\Models\Subscription;
use App\Services\VodafoneCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    protected $vodafoneCashService;

    public function __construct(VodafoneCashService $vodafoneCashService)
    {
        $this->vodafoneCashService = $vodafoneCashService;
    }

    /**
     * Get payment form for a course
     */
    public function getPaymentForm(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // Check if already subscribed
        if ($user->isSubscribedTo($courseId)) {
            return response()->json([
                'message' => 'Already subscribed to this course'
            ], 400);
        }

        // Check if payment already exists and pending
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'message' => 'Payment already pending for this course',
                'payment' => $existingPayment
            ], 400);
        }

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'price' => $course->price,
                'currency' => config('app.payment_currency', 'EGP')
            ],
            'payment_methods' => [
                'vodafone_cash' => [
                    'name' => 'Vodafone Cash',
                    'instructions' => 'Please enter your Vodafone Cash number and the number you will transfer from'
                ]
            ]
        ]);
    }

    /**
     * Submit Vodafone Cash payment
     */
    public function submitVodafonePayment(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'vodafone_number' => 'required|string|regex:/^01[0-9]{9}$/',
            'sender_number' => 'required|string|regex:/^01[0-9]{9}$/',
            'amount' => 'required|numeric|min:0',
            'transaction_reference' => 'nullable|string|max:255',
        ]);

        $course = Course::findOrFail($request->course_id);
        $user = $request->user();

        // Verify amount matches course price
        if ($request->amount != $course->price) {
            return response()->json([
                'message' => 'Payment amount does not match course price'
            ], 400);
        }

        // Check if already subscribed
        if ($user->isSubscribedTo($request->course_id)) {
            return response()->json([
                'message' => 'Already subscribed to this course'
            ], 400);
        }

        // Check if payment already exists
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('course_id', $request->course_id)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'message' => 'Payment already pending for this course'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'user_id' => $user->id,
                'course_id' => $request->course_id,
                'amount' => $request->amount,
                'currency' => config('app.payment_currency', 'EGP'),
                'payment_method' => 'vodafone_cash',
                'vodafone_number' => $request->vodafone_number,
                'sender_number' => $request->sender_number,
                'transaction_reference' => $request->transaction_reference,
                'status' => 'pending',
                'payment_data' => json_encode([
                    'vodafone_number' => $request->vodafone_number,
                    'sender_number' => $request->sender_number,
                    'transaction_reference' => $request->transaction_reference,
                    'submitted_at' => now()
                ])
            ]);

            DB::commit();

            Log::info('New Vodafone Cash payment submitted', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'course_id' => $request->course_id,
                'amount' => $request->amount
            ]);

            return response()->json([
                'message' => 'Payment submitted successfully. It will be reviewed by admin.',
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment submission failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'course_id' => $request->course_id
            ]);

            return response()->json([
                'message' => 'Payment submission failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Get pending payments for admin
     */
    public function getPendingPayments()
    {
        $payments = Payment::with(['user:id,name,email,phone', 'course:id,title,price'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'user' => $payment->user,
                    'course' => $payment->course,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'vodafone_number' => $payment->vodafone_number,
                    'sender_number' => $payment->sender_number,
                    'transaction_reference' => $payment->transaction_reference,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                    'payment_data' => $payment->payment_data ? json_decode($payment->payment_data, true) : null
                ];
            })
        ]);
    }

    /**
     * Approve payment
     */
    public function approvePayment(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Payment already processed'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $payment->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'admin_notes' => $request->admin_notes,
            ]);

            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->course_id,
                'payment_id' => $payment->id,
                'subscribed_at' => now(),
                'is_active' => true,
            ]);

            DB::commit();

            Log::info('Payment approved', [
                'payment_id' => $payment->id,
                'approved_by' => $request->user()->id,
                'subscription_id' => $subscription->id
            ]);

            return response()->json([
                'message' => 'Payment approved and subscription created',
                'payment' => $payment,
                'subscription' => $subscription
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment approval failed', [
                'payment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Payment approval failed'
            ], 500);
        }
    }

    /**
     * Reject payment
     */
    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
            'rejection_reason' => 'required|string|in:invalid_amount,invalid_transaction,duplicate_payment,insufficient_funds,other'
        ]);

        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Payment already processed'
            ], 400);
        }

        $payment->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'admin_notes' => $request->admin_notes,
            'rejection_reason' => $request->rejection_reason,
        ]);

        Log::info('Payment rejected', [
            'payment_id' => $payment->id,
            'rejected_by' => $request->user()->id,
            'reason' => $request->rejection_reason
        ]);

        return response()->json([
            'message' => 'Payment rejected',
            'payment' => $payment
        ]);
    }

    /**
     * Get user payment history
     */
    public function getUserPaymentHistory(Request $request)
    {
        $user = $request->user();

        $payments = Payment::with(['course:id,title,price'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'course' => $payment->course,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                    'approved_at' => $payment->approved_at,
                    'rejected_at' => $payment->rejected_at,
                    'admin_notes' => $payment->admin_notes,
                    'rejection_reason' => $payment->rejection_reason
                ];
            })
        ]);
    }

    /**
     * Get payment statistics for admin
     */
    public function getPaymentStats()
    {
        $stats = [
            'total_payments' => Payment::count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'approved_payments' => Payment::where('status', 'approved')->count(),
            'rejected_payments' => Payment::where('status', 'rejected')->count(),
            'total_revenue' => Payment::where('status', 'approved')->sum('amount'),
            'pending_revenue' => Payment::where('status', 'pending')->sum('amount'),
            'monthly_revenue' => Payment::where('status', 'approved')
                ->whereMonth('approved_at', now()->month)
                ->whereYear('approved_at', now()->year)
                ->sum('amount'),
            'daily_revenue' => Payment::where('status', 'approved')
                ->whereDate('approved_at', now()->toDateString())
                ->sum('amount')
        ];

        return response()->json($stats);
    }
}

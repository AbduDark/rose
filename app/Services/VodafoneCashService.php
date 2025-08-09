
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class VodafoneCashService
{
    protected $merchantId;
    protected $merchantKey;
    protected $baseUrl;
    protected $callbackUrl;

    public function __construct()
    {
        $this->merchantId = config('services.vodafone.merchant_id');
        $this->merchantKey = config('services.vodafone.merchant_key');
        $this->baseUrl = config('services.vodafone.base_url', 'https://api.vodafonecash.com.eg');
        $this->callbackUrl = config('services.vodafone.callback_url');
    }

    /**
     * Validate Vodafone Cash number format
     */
    public function validateVodafoneNumber($number)
    {
        // Remove any spaces or special characters
        $cleanNumber = preg_replace('/[^0-9]/', '', $number);
        
        // Check if it's a valid Egyptian mobile number starting with 010
        return preg_match('/^010[0-9]{8}$/', $cleanNumber);
    }

    /**
     * Validate Egyptian mobile number
     */
    public function validateMobileNumber($number)
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $number);
        
        // Check if it's a valid Egyptian mobile number
        return preg_match('/^01[0-9]{9}$/', $cleanNumber);
    }

    /**
     * Generate payment reference
     */
    public function generatePaymentReference($userId, $courseId)
    {
        return 'PAY_' . $userId . '_' . $courseId . '_' . time();
    }

    /**
     * Verify transaction with Vodafone Cash API (if available)
     */
    public function verifyTransaction($transactionReference, $amount, $vodafoneNumber)
    {
        try {
            // This would be the actual API call to Vodafone Cash
            // For development, we'll simulate the verification
            
            if (empty($this->merchantId) || empty($this->merchantKey)) {
                Log::warning('Vodafone Cash API credentials not configured');
                return [
                    'status' => 'manual_verification_required',
                    'message' => 'Manual verification required'
                ];
            }

            // Simulate API call
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->merchantKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/v1/verify-transaction', [
                'merchant_id' => $this->merchantId,
                'transaction_reference' => $transactionReference,
                'amount' => $amount,
                'vodafone_number' => $vodafoneNumber
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'pending',
                    'message' => $data['message'] ?? 'Transaction verified',
                    'transaction_id' => $data['transaction_id'] ?? null
                ];
            }

            return [
                'status' => 'verification_failed',
                'message' => 'Failed to verify transaction'
            ];

        } catch (\Exception $e) {
            Log::error('Vodafone Cash verification failed', [
                'error' => $e->getMessage(),
                'transaction_reference' => $transactionReference
            ]);

            return [
                'status' => 'error',
                'message' => 'Verification service unavailable'
            ];
        }
    }

    /**
     * Format amount for display
     */
    public function formatAmount($amount, $currency = 'EGP')
    {
        return number_format($amount, 2) . ' ' . $currency;
    }

    /**
     * Get payment instructions for users
     */
    public function getPaymentInstructions()
    {
        return [
            'steps' => [
                '1. Open your Vodafone Cash app',
                '2. Select "Send Money"',
                '3. Enter the merchant Vodafone Cash number',
                '4. Enter the exact amount shown',
                '5. Complete the transaction',
                '6. Enter your Vodafone Cash number and sender number in the form',
                '7. Submit the payment for verification'
            ],
            'notes' => [
                'Make sure to enter the exact amount',
                'Keep your transaction reference for tracking',
                'Payment will be verified by admin within 24 hours',
                'Contact support if you have any issues'
            ]
        ];
    }
}

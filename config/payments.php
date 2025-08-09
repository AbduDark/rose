
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for payment processing
    |
    */

    'currency' => env('PAYMENT_CURRENCY', 'EGP'),
    
    'methods' => [
        'vodafone_cash' => [
            'name' => 'Vodafone Cash',
            'enabled' => true,
            'icon' => 'vodafone-icon.svg',
            'instructions' => [
                'Open your Vodafone Cash app',
                'Select "Send Money"',
                'Enter the merchant number',
                'Enter the exact amount',
                'Complete the transaction',
                'Enter your details in the form'
            ]
        ]
    ],

    'statuses' => [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ],

    'rejection_reasons' => [
        'invalid_amount' => 'Invalid Amount',
        'invalid_transaction' => 'Invalid Transaction',
        'duplicate_payment' => 'Duplicate Payment',
        'insufficient_funds' => 'Insufficient Funds',
        'other' => 'Other'
    ]
];

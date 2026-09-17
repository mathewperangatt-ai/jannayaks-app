<?php

return [
    'tier_pricing' => [
        'currency' => 'INR',
        'gst_percent' => 18,
        'packages' => [
            'emerging' => [
                'label' => 'Emerging Leader',
                'base_amount' => 3000,
                'description' => 'Single upfront profile package.',
                'photo_slots' => 1,
                'includes_video_link' => false,
            ],
            'accomplished' => [
                'label' => 'Accomplished Leader',
                'base_amount' => 8000,
                'description' => 'Single upfront profile package.',
                'photo_slots' => 3,
                'includes_video_link' => true,
            ],
            'distinguished' => [
                'label' => 'Distinguished Leader',
                'base_amount' => 25000,
                'description' => 'Single upfront profile package.',
                'includes_video_link' => true,
                'photo_slots' => 5,
            ],
        ],
        'addons' => [
            'distinguished_in_person_interview' => [
                'label' => 'Distinguished In-Person Journalist Interview',
                // Sticker amount charged as stated (+₹10,000). Treated as GST-inclusive
                // provisionally until commercial/legal GST treatment is confirmed.
                'base_amount' => 10000,
                'gst_inclusive' => true,
            ],
        ],
        // Seller/legal fields for GST tax invoices. Leave blank until registration is confirmed.
        // Do not invent GSTIN or legal entity details.
        'billing' => [
            'legal_name' => env('JANNAYAKS_BILLING_LEGAL_NAME', ''),
            'gstin' => env('JANNAYAKS_BILLING_GSTIN', ''),
            'address' => env('JANNAYAKS_BILLING_ADDRESS', ''),
            'state' => env('JANNAYAKS_BILLING_STATE', ''),
            'place_of_supply' => env('JANNAYAKS_BILLING_PLACE_OF_SUPPLY', ''),
            'support_email' => env('JANNAYAKS_BILLING_SUPPORT_EMAIL', ''),
        ],
        'in_memoriam' => [
            'label' => 'In Memoriam (5 years hosting)',
            'base_amount' => 25000,
            'hosting_years' => 5,
        ],
        'membership' => [
            'label' => 'Annual Membership',
            'base_amount' => 2000,
            'cycle_years' => 1,
        ],
        'revision' => [
            'label' => 'Profile Revision / Update',
            'base_amount' => 2000,
            'description' => 'Interim profile revision or content update.',
        ],
    ],

    'refund' => [
        // Working assumption only — reconfirm before production. Not an immutable business rule.
        'before_publication_percent' => (int) env('JANNAYAKS_REFUND_BEFORE_PUBLICATION_PERCENT', 60),
        'refundable_statuses' => ['pending', 'success'],
    ],

    'renewal' => [
        'reminder_days_before' => [60, 30, 7],
        'reminder_days_after' => [1, 30, 60],
        'retention_days_after_expiry' => 365,
    ],

    'slug' => [
        'reserved' => [
            'admin',
            'api',
            'apply',
            'about',
            'contact',
            'membership',
            'in-memoriam',
            'login',
            'register',
            'browse',
            'search',
            'dashboard',
            'profile',
            'user',
            'users',
            'payment',
            'invoice',
            'receipt',
            'renewal',
            'billing',
            'consent',
        ],
        'redirect_expiry_days' => 365,
        'default_patterns' => ['simple', 'name_place', 'name_family', 'custom_handle'],
    ],

    'ai' => [
        'provider' => env('JANNAYAKS_AI_PROVIDER', 'gpt'),
        'malayalam_pipeline_enabled' => true,
    ],

    'security' => [
        'test_bypass_role' => 'admin',
    ],

    'otp' => [
        // P7 delivery is log/test-cache only. No SMS provider is implemented.
        // Do not set sms_enabled=true until a real provider exists in a later phase.
        'expiry_minutes' => 10,
        'channel' => env('JANNAYAKS_OTP_CHANNEL', 'log'), // log | test-cache metadata only
        'sms_enabled' => false,
        'expose_test_code' => env('JANNAYAKS_OTP_EXPOSE_TEST_CODE', false),
        'log_plaintext_in_non_production' => true, // logs length only, never the OTP value
    ],
];

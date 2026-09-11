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
                'base_amount' => 10000,
            ],
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
    ],

    'refund' => [
        'before_publication_percent' => 60,
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
];

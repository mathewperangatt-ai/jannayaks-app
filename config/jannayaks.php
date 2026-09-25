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
            'label' => 'In Memoriam (3 years hosting)',
            // Sticker amount shown to customers; treated as GST-inclusive like living packages.
            'base_amount' => 25000,
            'gst_inclusive' => true,
            'hosting_years' => 3,
            'photo_slots' => (int) env('JANNAYAKS_IN_MEMORIAM_PHOTO_SLOTS', 20),
            'contact_email' => env(
                'JANNAYAKS_IN_MEMORIAM_CONTACT_EMAIL',
                env('JANNAYAKS_BILLING_SUPPORT_EMAIL', 'hello@jannayaks.in')
            ),
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
        // Provisional reminder offsets — final schedule/copy undecided (P15).
        'reminder_days_before' => [60, 30, 7],
        'reminder_days_after' => [1, 30, 60],
        'retention_days_after_expiry' => 365,
    ],

    /*
    | Membership lifecycle (Phase 15).
    | Business calendar days use membership_lifecycle.business_timezone (default Asia/Kolkata).
    | This does NOT change config('app.timezone') / UTC used elsewhere (payments, timestamps).
    | Grace: profile stays public for N calendar days after ends_on.
    | Deactivation eligible when today > ends_on + grace_period_days.
    | Retention: ends_on + retention_years (not the deactivation timestamp). Post-retention undecided.
    | Reminder day offsets are provisional configuration only.
    */
    'membership_lifecycle' => [
        'business_timezone' => env('MEMBERSHIP_BUSINESS_TIMEZONE', 'Asia/Kolkata'),
        'grace_period_days' => (int) env('MEMBERSHIP_GRACE_PERIOD_DAYS', 3),
        'retention_years' => (int) env('MEMBERSHIP_RETENTION_YEARS', 1),
        // Provisional defaults — override in tests / env as needed; not finalized product schedule.
        'reminder_days_before_expiry' => [60, 30, 7],
        'reminder_days_after_expiry' => [1],
        'scheduler' => [
            // Application-side schedule. Production still needs external cron for `php artisan schedule:run`.
            'daily_at' => env('MEMBERSHIP_LIFECYCLE_DAILY_AT', '01:15'),
        ],
    ],

    'slug' => [
        // Reserved against collision with application/system routes (inspect routes/web.php + Filament).
        // Admin-manageable extras live in reserved_slugs; this list is the system baseline.
        'reserved' => [
            'admin',
            'api',
            'apply',
            'about',
            'auth',
            'billing',
            'browse',
            'consent',
            'contact',
            'dashboard',
            'faq-charges',
            'filament',
            'gallery',
            'home',
            'in-memoriam',
            'invoice',
            'livewire',
            'login',
            'logout',
            'membership',
            'p',
            'payment',
            'payments',
            'privacy',
            'profile',
            'profiles',
            'receipt',
            'register',
            'renewal',
            'search',
            'staff',
            'storage',
            'terms',
            'up',
            'user',
            'users',
            'applications',
            'application',
        ],
        // 0 = never auto-expire. Historical /{slug} redirects remain indefinite.
        // A non-null expires_at may still be set manually for legal/security disablement.
        'redirect_expiry_days' => 0,
        'default_patterns' => ['simple', 'name_place', 'name_family', 'custom_handle'],
    ],

    'contact' => [
        'public_email' => env('JANNAYAKS_PUBLIC_EMAIL', 'hello@jannayaks.in'),
        'public_phone' => env('JANNAYAKS_PUBLIC_PHONE', '94 95 94 93 99'),
        'public_phone_tel' => env('JANNAYAKS_PUBLIC_PHONE_TEL', '+919495949399'),
        'legal_address' => env('JANNAYAKS_LEGAL_ADDRESS', '3/532, Trivandrum 695573'),
    ],

    'geography' => [
        'current_state_name' => 'Keralam',
        'current_country_code' => 'IN',
        'current_country_name' => 'India',
    ],

    'ai' => [
        'provider' => env('JANNAYAKS_AI_PROVIDER', 'gpt'), // gpt | fake
        'malayalam_pipeline_enabled' => (bool) env('JANNAYAKS_AI_MALAYALAM_ENABLED', true),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'model' => env('JANNAYAKS_OPENAI_MODEL', 'gpt-4.1-mini'),
            'endpoint' => env('JANNAYAKS_OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'timeout_seconds' => (int) env('JANNAYAKS_OPENAI_TIMEOUT', 60),
        ],
        'editorial' => [
            // Two included pre-publication revision rounds are part of initial prep (P11). Not paid.
            'included_prepublication_revision_rounds' => 2,
            // Post-publication meaningful revision uses tier_pricing.revision (₹2,000 + GST) — not in P10.
        ],
    ],

    'security' => [
        'test_bypass_role' => 'admin',

        /*
        | Published Profile Integrity Monitor (Wave 2C, REPORT-ONLY).
        | mode: only "report" exists in this wave — the watchdog records
        | incidents and NEVER suspends or modifies content. Enforce mode is a
        | separate future wave after burn-in.
        | deep_verify_percent: share of published primary photos receiving a
        | full streamed SHA-256 verification each run (others get a cheap
        | size/HEAD check). Default 100 is correct at pre-launch scale
        | (currently a handful of photos); lower it (e.g. 10) as the corpus
        | grows — the rotation is deterministic per profile per day.
        */
        'integrity' => [
            'mode' => env('JANNAYAKS_INTEGRITY_MODE', 'report'),
            'deep_verify_percent' => (int) env('JANNAYAKS_INTEGRITY_DEEP_PERCENT', 100),
        ],

        /*
        | HTTP response security headers (app/Http/Middleware/SetSecurityHeaders.php).
        | The CSP below is derived from the resources the application actually loads:
        | self-hosted assets, Google Fonts stylesheets + gstatic font files, the
        | Google Translate widget on the homepage, and pervasive inline
        | scripts/styles (see docs/security-headers-csp.md for the report-only
        | rationale and the enforcement path). 'unsafe-eval' is deliberately absent.
        */
        'headers' => [
            // Report-Only until production reports are clean; then set true.
            'csp_enforce' => env('JANNAYAKS_CSP_ENFORCE', false),
            'csp' => implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "img-src 'self' data:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "script-src 'self' 'unsafe-inline' https://translate.google.com https://translate.googleapis.com",
                "connect-src 'self' https://translate.googleapis.com",
                "frame-src https://translate.googleapis.com",
            ]),
        ],
    ],

    'otp' => [
        // P7 delivery is log/test-cache only. No SMS provider is implemented.
        // Do not set sms_enabled=true until a real provider exists in a later phase.
        // Set login_enabled=false in production until a real SMS provider is wired up,
        // so the UI never claims an OTP was sent when nothing can be delivered.
        'login_enabled' => env('JANNAYAKS_OTP_LOGIN_ENABLED', true),
        'expiry_minutes' => 10,
        'channel' => env('JANNAYAKS_OTP_CHANNEL', 'log'), // log | test-cache metadata only
        'sms_enabled' => false,
        'expose_test_code' => env('JANNAYAKS_OTP_EXPOSE_TEST_CODE', false),
        'log_plaintext_in_non_production' => true, // logs length only, never the OTP value
    ],

    /*
    | Profile media (Phase 14). Public photographs vs private source materials stay separate.
    | MEDIA_PUBLIC_DISK defaults to local "public" for development/tests; set to "r2" in production.
    | Member uploads are always pending_review until editorial staff approve them.
    | Uploads are resized/compressed server-side; originals are never stored on the media disk.
    */
    'media' => [
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
        'object_prefix' => 'profile-media',
        'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 5120),
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'forbidden_extensions' => [
            'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8',
            'exe', 'bat', 'cmd', 'com', 'dll', 'so', 'sh', 'bash',
            'js', 'mjs', 'html', 'htm', 'shtml', 'svg', 'svgz', 'xml',
            'mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v', 'mpeg', 'mpg',
        ],
        /*
        | Optimization for premium editorial portraits on web:
        | 1600px longest edge is high enough for hero/portrait display on retina
        | screens while cutting multi‑megabyte phone originals substantially.
        | Stored output is always JPEG at the configured quality.
        */
        'optimization' => [
            'max_edge_px' => (int) env('MEDIA_MAX_EDGE_PX', 1600),
            'jpeg_quality' => (int) env('MEDIA_JPEG_QUALITY', 82),
        ],
        'video_links' => [
            // Technical abuse ceiling — not a product-tier entitlement.
            'max_per_profile' => (int) env('MEDIA_VIDEO_LINKS_MAX_PER_PROFILE', 10),
        ],
    ],
];

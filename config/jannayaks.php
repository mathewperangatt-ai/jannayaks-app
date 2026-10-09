<?php

return [
    /*
    | Payments testing mode (TEMPORARY — pre-gateway-launch).
    | While true, the payment requirement is suspended for everyone: new
    | testers can complete the whole workflow (interview, uploads) without
    | paying, payment initiation endpoints are refused, and the payment page
    | explains the suspension. Nothing records a fake settlement —
    | applications simply stay in their pre-payment state. The genuine
    | settlement path (webhook → markPaidOrCaptured → afterSettled), the
    | allowlisted payments:simulate-test-payment command, and all payment
    | models/records are untouched.
    | Restore the normal payment-required workflow by setting
    | JANNAYAKS_PAYMENTS_TESTING_MODE=false (or removing the variable) — no
    | database reset or migration involved. Enabling real payments ALSO
    | requires services.razorpay.enabled=true with authorized credentials;
    | this flag alone never activates the gateway.
    */
    'payments' => [
        'testing_mode' => env('JANNAYAKS_PAYMENTS_TESTING_MODE', false),
    ],

    'tier_pricing' => [
        'currency' => 'INR',
        'gst_percent' => 18,
        /*
        | Commercial model (Master Editorial Specification §12, confirmed):
        | Internal storage keys are retained (emerging/accomplished/distinguished);
        | customer-facing tier names come from these labels / App\Support\TierLabels.
        | base_amount is EXCLUSIVE of GST — the customer pays base + GST at checkout.
        | Annual membership renewal is tier-priced (same annual tier price).
        */
        'packages' => [
            'emerging' => [
                'label' => 'Recognised',
                'base_amount' => 3000,
                'description' => 'Annual profile package.',
                'photo_slots' => 1,
                'includes_video_link' => false,
            ],
            'accomplished' => [
                'label' => 'Acclaimed',
                'base_amount' => 6000,
                'description' => 'Annual profile package.',
                'photo_slots' => 3,
                'includes_video_link' => true,
            ],
            'distinguished' => [
                'label' => 'Distinguished',
                'base_amount' => 9000,
                'description' => 'Annual profile package.',
                'includes_video_link' => true,
                'photo_slots' => 5,
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
            // ₹24,000 + GST (exclusive) per the confirmed commercial model.
            'base_amount' => 24000,
            'gst_inclusive' => false,
            'hosting_years' => 3,
            'photo_slots' => (int) env('JANNAYAKS_IN_MEMORIAM_PHOTO_SLOTS', 20),
            'contact_email' => env(
                'JANNAYAKS_IN_MEMORIAM_CONTACT_EMAIL',
                env('JANNAYAKS_BILLING_SUPPORT_EMAIL', 'hello@jannayaks.in')
            ),
        ],
        /*
        | Flat-rate 'membership' block removed: annual membership renewal is now
        | tier-priced at the member's applicable annual tier price (see
        | PricingAmounts::forTierRenewal()). Cycle length remains 1 year.
        */
        'membership' => [
            'label' => 'Annual Membership',
            'cycle_years' => 1,
        ],
        'revision' => [
            'label' => 'Profile Revision / Update',
            'base_amount' => 2000,
            'description' => 'Interim profile revision or content update.',
        ],
    ],

    /*
    | SEO-1: shared social/share metadata defaults. The temporary share image is
    | the approved square logo; swap 'share_image' for the branded 1200x630
    | artwork later without touching the metadata architecture.
    */
    'seo' => [
        'site_name' => 'Jannayaks',
        'share_image' => 'branding/jannayaks-logo-approved.jpg',
        'twitter_card' => 'summary',
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
            'demo-profiles',
            'disclaimer',
            'faq-charges',
            'filament',
            'gallery',
            'grievance',
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
            'refund-policy',
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
        'legal_address' => env('JANNAYAKS_LEGAL_ADDRESS', '3/352, Trivandrum, Kerala 695573'),
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
        'image_enhancement' => [
            // KILL SWITCH — disabled by default. When false: no candidate is
            // created, no job is dispatched, uploads/approval/publication are
            // completely unaffected. Enable only after output-quality review.
            // NULL (env unset) means "follow payments.testing_mode" — during
            // the temporary testing period the feature is on so testers can
            // exercise the full workflow, and it auto-disables when testing
            // mode ends. An explicit env value always wins.
            'enabled' => env('JANNAYAKS_AI_ENHANCEMENT_ENABLED'),
            // Independent from the editorial provider selection.
            'provider' => env('JANNAYAKS_AI_IMAGE_PROVIDER', 'fake'), // fake | openai
            'max_retries' => (int) env('JANNAYAKS_AI_ENHANCEMENT_MAX_RETRIES', 3),
            'openai' => [
                // Shares the existing OpenAI secret convention but nothing else.
                'api_key' => env('OPENAI_API_KEY', ''),
                'model' => env('JANNAYAKS_AI_IMAGE_MODEL', 'gpt-image-1'),
                'endpoint' => env('JANNAYAKS_AI_IMAGE_ENDPOINT', 'https://api.openai.com/v1/images/edits'),
                'timeout_seconds' => (int) env('JANNAYAKS_AI_IMAGE_TIMEOUT', 180),
                'size' => env('JANNAYAKS_AI_IMAGE_SIZE', '1024x1024'),
            ],
            // Master enhancement direction. Identity preservation is absolute.
            'prompt' => <<<'PROMPT'
Create a natural, dignified professional portrait suitable for the Jannayaks public profile. Preserve the person's identity, facial structure, approximate real age, natural skin tone, distinctive features and hairstyle. Use soft natural daylight, realistic skin texture and an approachable, confident expression with a subtle natural smile — but if the person's natural expression is serious, preserve that seriousness instead of forcing a smile. Use an eye-level camera position and a slight three-quarter angle where appropriate. Frame as a head-and-shoulders or upper-torso portrait with comfortable space around the head. Use a realistic, softly blurred community, outdoor, workplace or culturally appropriate background that provides context without distracting from the person. Clothing should be simple, respectable, age-appropriate and culturally plausible. The result should look like an excellent real photograph rather than an AI fashion portrait, corporate headshot or political publicity image.

Absolute rule: for real people, enhance the photograph, don't reinvent the person. The output must remain recognisably the same person.

Strictly avoid: changing facial structure or apparent identity; changing approximate age; skin whitening; excessive skin smoothing or removing normal wrinkles; changing body shape; changing hairstyle unnecessarily; inventing a different person; glamour or fashion treatment; corporate executive styling; political publicity styling; dramatic or artificial cinematic lighting; exaggerated smiles or power poses; political symbols; text, logos, badges, borders or tier labels.

Allowed: exposure, lighting, sharpness, noise reduction, colour balance, natural skin rendering, background distraction reduction, composition, minor photographic imperfections, professional finishing.
PROMPT,
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
        | self-hosted assets, Google Fonts stylesheets + gstatic font files, and the
        | Google Translate widget on the homepage (see docs/security-headers-csp.md).
        | 'unsafe-eval' is deliberately absent from the member policy; the admin
        | policy adds it because Filament's Alpine build evaluates expressions via
        | the Function constructor, which an enforced CSP blocks without it.
        | MSG91/OTP hosts were removed along with the OTP authentication retirement.
        */
        'headers' => [
            // Enforced by default with the member-safe policy; set to false to
            // fall back to Report-Only while debugging a violation report.
            'csp_enforce' => env('JANNAYAKS_CSP_ENFORCE', true),
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
            // Filament admin panel (Livewire + Alpine): identical to the member
            // policy except script-src gains 'unsafe-eval', which Alpine's
            // expression evaluation requires under an enforced CSP.
            'csp_admin' => implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "img-src 'self' data:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://translate.google.com https://translate.googleapis.com",
                "connect-src 'self' https://translate.googleapis.com",
                "frame-src https://translate.googleapis.com",
            ]),
        ],
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

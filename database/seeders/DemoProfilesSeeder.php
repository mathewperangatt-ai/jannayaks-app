<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\InMemoriamEditorialContent;
use App\Models\InMemoriamGeography;
use App\Models\InMemoriamProfile;
use App\Models\InMemoriamPublicOffice;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileGeography;
use App\Models\ProfilePublicOffice;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\ProfileUrlService;
use App\Support\TierLabels;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FINAL DEMONSTRATION POPULATION.
 *
 * Living demonstrations (12) — seeded through the application's REAL
 * editorial/publication workflow so each record is structurally identical
 * to a legitimately published profile (approval, version-pinned customer
 * approval, durable consent, admin publication, membership, reserved slug,
 * audit trail):
 *
 *   3 Recognised  — Fr. Joseph Mathew, K. Suresh Menon, P. Devika Nair
 *   4 Acclaimed   — Adv. Lakshmi Raghavan, Dr. N. Haridas, Shobha Menon,
 *                   V. Krishnakumar
 *   5 Distinguished — Adv. Farid Khan, Latha Varghese, R. Madhavan Nair,
 *                   T. N. Raghavan, T. Gopalakrishnan (recovered approved
 *                   English text used verbatim)
 *
 * The earlier Recognised T. Gopalakrishnan stress-test text is retired.
 *
 * Memorial demonstrations (2) — K. V. Mathew and Dr. Saroja Nair. No
 * approved final memorial text was recovered: the body is a clearly-marked
 * TEMPORARY state built only from the confirmed identity facts, awaiting
 * insertion of the approved final text. Do not treat it as approved copy.
 *
 * Portraits: place approved images in database/seeders/demo-media/ using
 * the filenames referenced in demo-profiles-living.php (t_gopalakrishnan.jpg,
 * k_v_mathew_memorial_portrait.jpg, dr_saroja_nair_memorial_portrait.jpg)
 * and re-run the seeder. Missing files render the dignified monogram
 * placeholder. The extracted Kerala-gallery portraits belong to different
 * identities and are intentionally NOT cross-assigned.
 *
 * Demo exclusion from the sitemap uses the established internal-email
 * convention (all demo accounts live under @jannayaks.internal).
 *
 * Run: php artisan db:seed --class=DemoProfilesSeeder --force
 */
class DemoProfilesSeeder extends Seeder
{
    private User $demoAdmin;

    public function run(): void
    {
        $living = require database_path('seeders/demo-profiles-living.php');

        $this->demoAdmin = User::query()->updateOrCreate(
            ['email' => 'demo.admin@jannayaks.internal'],
            [
                'name' => 'Jannayaks Demo Editorial',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        foreach ($living as $slug => $entry) {
            $this->seedLivingDemo($slug, $entry);
        }

        $this->seedMemorials();
        $this->seedTestAccounts();
    }

    /* -----------------------------------------------------------------
     * Living demonstrations — through the real editorial/publication
     * workflow (release → version-pinned customer approval → admin
     * publication). Idempotent: fully published demos are skipped;
     * partially progressed demos resume at their current stage.
     * ----------------------------------------------------------------- */
    private function seedLivingDemo(string $slug, array $entry): void
    {
        $tier = (string) $entry['tier'];
        $workflow = app(CustomerEditorialWorkflowService::class);

        // 1. Demo customer account (internal demo domain → excluded from the
        //    sitemap by the established convention; never a real member).
        $owner = User::query()->updateOrCreate(
            ['email' => "demo.$slug@jannayaks.internal"],
            [
                'name' => $entry['name'],
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        // 2. Profile — matched by the owning user (profiles.user_id is the
        //    natural unique key; emerging demos change slug at publication,
        //    so the slug is not a stable lookup key across re-runs).
        $personalSlug = in_array($tier, ProfileUrlService::PERSONAL_TIERS, true) ? $slug : null;

        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $owner->id],
            [
                'status' => 'under_editorial_review',
                'full_name' => $entry['name'],
                'display_name' => $entry['name'],
                'profession' => $entry['profession'],
                'bio_headline' => $entry['profession'],
                'slug' => $personalSlug,
                'slug_generated_at' => $personalSlug !== null ? now() : null,
                'slug_changed_at' => $personalSlug !== null ? now() : null,
                'published_at' => null,
                'display_phone_consent' => false,
                'display_email_consent' => false,
            ],
        );

        $profile->ensureMandatoryGeography();

        // 3. Approved editorial content (EN master + linked ML adaptation),
        //    written by the demo editorial desk — not through the AI pipeline.
        $english = EditorialContent::query()->firstOrCreate(
            [
                'profile_id' => $profile->id,
                'language' => EditorialContent::LANGUAGE_EN,
                'version_number' => 1,
            ],
            [
                'title' => $entry['en']['title'],
                'summary' => $entry['en']['summary'],
                'body' => $entry['en']['body'],
                'status' => EditorialContent::STATUS_APPROVED,
                'ai_generated' => false,
                'reviewed_by_id' => $this->demoAdmin->id,
            ],
        );

        $malayalam = null;
        if ($entry['ml'] !== null) {
            $malayalam = EditorialContent::query()->firstOrCreate(
                [
                    'profile_id' => $profile->id,
                    'language' => EditorialContent::LANGUAGE_ML,
                    'version_number' => 1,
                ],
                [
                    'title' => $entry['ml']['title'],
                    'summary' => $entry['ml']['summary'],
                    'body' => $entry['ml']['body'],
                    'status' => EditorialContent::STATUS_APPROVED,
                    'source_editorial_content_id' => $english->id,
                    'ai_generated' => false,
                    'reviewed_by_id' => $this->demoAdmin->id,
                ],
            );
        }

        // 4. Application (admin_test_demo source = the existing controlled
        //    payment-waiver lane for internal demonstrations).
        $application = Application::query()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'user_id' => $owner->id,
                'source_method' => 'admin_test_demo',
                'package_tier' => $tier,
                'full_name' => $entry['name'],
                'preferred_display_name' => $entry['name'],
                'preferred_slug' => $personalSlug,
                'preferred_contact_email' => "demo.$slug@jannayaks.internal",
                'payment_status' => 'waived',
                'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
                'intake_started_at' => now()->subDays(20),
                'admin_demo_audit_note' => 'Demonstration profile population (idempotent seeder).',
            ],
        );

        // 5. Walk the real workflow from the application's current stage.
        if ($application->status === Application::STATUS_PUBLISHED) {
            $this->attachPortrait($profile, $entry['portrait'] ?? null, 'Portrait of '.$entry['name']);
            $this->command?->info(sprintf('Demo living profile (already published): %s — %s', $entry['name'], TierLabels::label($tier)));

            return;
        }

        $application = $workflow->releaseForCustomerPreview($application->fresh(), $this->demoAdmin);

        if ($application->status === Application::STATUS_EDITORIAL_APPROVED) {
            $workflow->approvePreview(
                $application->fresh(),
                $owner,
                (int) $application->preview_english_editorial_content_id,
                '127.0.0.1',
                'demo-seeder',
            );
        }

        $application->refresh();

        if ($application->status === Application::STATUS_AWAITING_PUBLICATION) {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $this->demoAdmin);
        }

        $application->refresh();
        $profile->refresh();

        $this->attachPortrait($profile, $entry['portrait'] ?? null, 'Portrait of '.$entry['name']);

        $this->command?->info(sprintf(
            'Demo living profile: %s — %s (slug: %s, application #%d, status: %s).',
            $entry['name'],
            TierLabels::label($tier),
            $profile->fresh()->slug,
            $application->id,
            $application->status,
        ));
    }

    /* -----------------------------------------------------------------
     * Memorial demonstrations (temporary editorial state — see notes).
     * ----------------------------------------------------------------- */
    private function seedMemorials(): void
    {
        $memorials = [
            [
                'slug' => 'k.v.mathew',
                'name' => 'K. V. Mathew',
                'display' => 'K. V. Mathew .late',
                'profession' => 'Teacher',
                'headline' => 'Teacher, Institution Builder, Mentor',
                'headline_ml' => 'അധ്യാപകൻ · സ്ഥാപന നിർമ്മാതാവ് · പരിശീലകൻ',
                'born' => '1945-01-01',
                'died' => '2021-01-01',
                'portrait' => 'k_v_mathew_memorial_portrait.jpg',
                'summary' => 'K. V. Mathew (1945–2021) is remembered as a teacher, institution builder and mentor.',
                'summary_ml' => 'കെ. വി. മാത്യു (1945–2021) അധ്യാപകനും സ്ഥാപന നിർമ്മാതാവും പരിശീലകനുമായി ഓർമ്മിക്കപ്പെടുന്നു.',
            ],
            [
                'slug' => 'dr.saroja.nair',
                'name' => 'Dr. Saroja Nair',
                'display' => 'Dr. Saroja Nair .late',
                'profession' => 'Doctor',
                'headline' => "Pioneer in Women's Healthcare",
                'headline_ml' => 'വനിതാ ആരോഗ്യ പരിചരണത്തിലെ മുൻനിര പ്രവർത്തക',
                'born' => '1952-01-01',
                'died' => '2020-01-01',
                'portrait' => 'dr_saroja_nair_memorial_portrait.jpg',
                'summary' => "Dr. Saroja Nair (1952–2020) is remembered as a pioneer in women's healthcare.",
                'summary_ml' => 'ഡോ. സരോജ നായർ (1952–2020) വനിതാ ആരോഗ്യ പരിചരണത്തിലെ മുൻനിര പ്രവർത്തകയായി ഓർമ്മിക്കപ്പെടുന്നു.',
            ],
        ];

        foreach ($memorials as $m) {
            $profile = InMemoriamProfile::query()->updateOrCreate(
                ['slug' => $m['slug']],
                [
                    'deceased_full_name' => $m['name'],
                    // ".late" is the approved memorial presentation suffix, applied
                    // at the display-name layer only.
                    'deceased_display_name' => $m['display'],
                    'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
                    'profession' => $m['profession'],
                    'bio_headline' => $m['headline'],
                    'deceased_date_of_birth' => $m['born'],
                    'deceased_date_of_death' => $m['died'],
                    'verification_status' => InMemoriamProfile::VERIFICATION_WAIVED, // fictional demonstration profile
                    'commissioner_display_consent' => false,
                    'commissioner_contact_name' => 'Jannayaks Demonstration Records',
                    'commissioner_contact_mobile' => '0000000000',
                    'commissioner_contact_email' => 'demo.profiles@jannayaks.internal',
                    'commissioner_relation' => 'Fictional demonstration record',
                    'published_at' => now()->subDays(20),
                    'hosting_starts_on' => now()->subYears(2)->startOfYear(),
                    'hosting_ends_on' => now()->addYears(10)->endOfYear(),
                ],
            );

            InMemoriamGeography::query()->updateOrCreate(
                ['in_memoriam_profile_id' => $profile->id],
                ['country_code' => 'IN', 'state_region_name' => 'Kerala'],
            );

            InMemoriamPublicOffice::query()->firstOrCreate(
                ['in_memoriam_profile_id' => $profile->id, 'office_name' => $m['headline']],
                ['where_location' => 'Kerala', 'is_current' => false, 'sort_order' => 1],
            );

            // TEMPORARY content state (EN + ML): the recovered corpus marks the
            // approved final memorial text as NOT LOCATED / DO NOT INVENT. These
            // bodies state only the confirmed identity facts. Replace with the
            // approved final text when available.
            $bodyEn = $m['summary']
                ."\n\nThis memorial demonstration page is shown with a temporary editorial state. "
                .'The complete life record for '.$m['name'].' is in preparation and will be published here.';
            $bodyMl = $m['summary_ml']
                ."\n\nഈ ഓർമ്മാഘട്ട ഡെമോൺസ്ട്രേഷൻ താൾ താൽക്കാലിക എഡിറ്റോറിയൽ നിലയിലാണ് പ്രദർശിപ്പിച്ചിരിക്കുന്നത്. "
                .$m['name'].'-ന്റെ പൂർണ്ണമായ ജീവിതരേഖ തയ്യാറാക്കി ഇവിടെ പ്രസിദ്ധീകരിക്കുന്നതാണ്.';

            InMemoriamEditorialContent::query()->updateOrCreate(
                ['in_memoriam_profile_id' => $profile->id, 'language' => InMemoriamEditorialContent::LANGUAGE_EN, 'version_number' => 1],
                [
                    'title' => $m['name'],
                    'summary' => $m['headline'],
                    'body' => $bodyEn,
                    'status' => InMemoriamEditorialContent::STATUS_APPROVED,
                    'ai_generated' => false,
                ],
            );

            InMemoriamEditorialContent::query()->updateOrCreate(
                ['in_memoriam_profile_id' => $profile->id, 'language' => InMemoriamEditorialContent::LANGUAGE_ML, 'version_number' => 1],
                [
                    'title' => $m['name'],
                    'summary' => $m['summary_ml'],
                    'body' => $bodyMl,
                    'status' => InMemoriamEditorialContent::STATUS_APPROVED,
                    'ai_generated' => false,
                ],
            );

            $this->attachMemorialPortrait($profile, $m['portrait'], 'Portrait of '.$m['name']);

            $this->command?->warn(sprintf(
                '%s: temporary content state — approved final memorial text was not located; do not treat as final copy.',
                $m['name'],
            ));
        }
    }

    /* -----------------------------------------------------------------
     * Internal test accounts (controlled payment-waiver lane).
     * ----------------------------------------------------------------- */
    private function seedTestAccounts(): void
    {
        $accounts = [
            ['Test Customer — Recognised', 'test.recognised@jannayaks.in', 'emerging'],
            ['Test Customer — Distinguished', 'test.distinguished@jannayaks.in', 'distinguished'],
        ];

        foreach ($accounts as [$name, $email, $tier]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(40)), // set a real password via admin before use
                    'email_verified_at' => now(),
                ],
            );

            $application = Application::query()->firstOrCreate(
                ['user_id' => $user->id, 'source_method' => 'admin_test_demo'],
                [
                    'package_tier' => $tier,
                    'full_name' => $name,
                    'preferred_display_name' => $name,
                    'payment_status' => 'waived',
                    'status' => 'payment_pending',
                    'waived_by_user_id' => $this->demoAdmin->id,
                    'admin_demo_audit_note' => 'Controlled internal test account for payment-unlocked journey testing.',
                    'intake_started_at' => now(),
                ],
            );

            $this->command?->info(sprintf('Test account: %s (%s) — application #%d (admin_test_demo waiver).', $email, $tier, $application->id));
        }
    }

    /* -----------------------------------------------------------------
     * Portrait attachment (approved files only; missing → monogram).
     * ----------------------------------------------------------------- */
    private function attachPortrait(Profile $profile, ?string $filename, string $alt): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $source = database_path('seeders/demo-media/'.$filename);
        if (! is_file($source)) {
            $this->command?->warn("Portrait not bundled yet: {$filename} (skipping — placeholder will be shown).");

            return;
        }

        $bytes = file_get_contents($source);
        if ($bytes === false) {
            return;
        }

        $disk = (string) config('jannayaks.media.public_disk', 'public');
        $prefix = trim((string) config('jannayaks.media.object_prefix', 'profile-media'), '/');
        $key = $prefix.'/profiles/'.$profile->id.'/'.Str::lower((string) Str::ulid()).'.jpg';
        Storage::disk($disk)->put($key, $bytes);

        $dimensions = @getimagesizefromstring($bytes);
        [$width, $height] = $dimensions ?: [null, null];

        MediaItem::query()->updateOrCreate(
            [
                'mediable_type' => $profile->getMorphClass(),
                'mediable_id' => $profile->id,
                'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
                'is_primary' => true,
            ],
            [
                'storage_path_key' => $key,
                'disk' => $disk,
                'alt_text' => $alt,
                'display_order' => 1,
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'review_status' => MediaItem::REVIEW_APPROVED,
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($bytes),
                'photo_sha256' => hash('sha256', $bytes),
                'width' => $width,
                'height' => $height,
            ],
        );
    }

    private function attachMemorialPortrait(InMemoriamProfile $profile, string $filename, string $alt): void
    {
        $source = database_path('seeders/demo-media/'.$filename);
        if (! is_file($source)) {
            $this->command?->warn("Memorial portrait not bundled yet: {$filename} (skipping — placeholder will be shown).");

            return;
        }

        $bytes = file_get_contents($source);
        if ($bytes === false) {
            return;
        }

        $disk = (string) config('jannayaks.media.public_disk', 'public');
        $prefix = trim((string) config('jannayaks.media.object_prefix', 'profile-media'), '/');
        $key = $prefix.'/in-memoriam/'.$profile->id.'/'.Str::lower((string) Str::ulid()).'.jpg';
        Storage::disk($disk)->put($key, $bytes);

        $dimensions = @getimagesizefromstring($bytes);
        [$width, $height] = $dimensions ?: [null, null];

        // Memorial photographs share the polymorphic MediaItem table
        // (InMemoriamMediaService writes the same shape).
        \App\Models\MediaItem::query()->updateOrCreate(
            [
                'mediable_type' => $profile->getMorphClass(),
                'mediable_id' => $profile->id,
                'media_type' => \App\Models\MediaItem::TYPE_PROFILE_PHOTO,
                'is_primary' => true,
            ],
            [
                'storage_path_key' => $key,
                'disk' => $disk,
                'alt_text' => $alt,
                'display_order' => 1,
                'privacy' => \App\Models\MediaItem::PRIVACY_PUBLIC,
                'review_status' => \App\Models\MediaItem::REVIEW_APPROVED,
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($bytes),
                'photo_sha256' => hash('sha256', $bytes),
                'width' => $width,
                'height' => $height,
            ],
        );
    }
}

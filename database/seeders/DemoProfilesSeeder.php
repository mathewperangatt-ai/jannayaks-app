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
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileUrlService;
use App\Support\TierLabels;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FINAL DEMONSTRATION POPULATION (corrected demonstration corpus).
 *
 * Living demonstrations (10) — seeded through the application's REAL
 * editorial/publication workflow so each record is structurally identical
 * to a legitimately published profile (approval, version-pinned customer
 * approval, durable consent, admin publication, membership, reserved slug,
 * audit trail):
 *
 *   3 Recognised    — Fr. Joseph Mathew, P. Rajeev Menon, S. Beena Kumari
 *   4 Acclaimed     — K. Shafiq Rahman, R. Leelamma, C. Manoj Kumar,
 *                     A. Mariamma
 *   3 Distinguished — T. Gopalakrishnan, P. Sreedharan, V. Suresh Babu
 *
 * Memorial demonstrations (2) — K. V. Mathew (Late) and Dr. Saroja Nair
 * (Late), with the supplied English and Malayalam descriptions. The corpus
 * supplies no dates or locations, so none are recorded.
 *
 * Content lives in demo-profiles-living.php and demo-profiles-memorial.php;
 * portraits (each carrying the visible "AI GENERATED" watermark) live in
 * database/seeders/demo-media/. Missing files render the monogram
 * placeholder. Living portraits are attached before publication so the
 * publication integrity snapshot already includes them.
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
            if ($this->attachPortrait($profile, $entry['portrait'] ?? null, 'Portrait of '.$entry['name'])) {
                app(ProfileIntegrityService::class)->refreshSnapshot($profile, 'demo_profiles_seeder.portrait_replaced');
            }
            $this->command?->info(sprintf('Demo living profile (already published): %s — %s', $entry['name'], TierLabels::label($tier)));

            return;
        }

        $this->attachPortrait($profile, $entry['portrait'] ?? null, 'Portrait of '.$entry['name']);

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
     * Memorial demonstrations (supplied corpus text — see notes).
     * ----------------------------------------------------------------- */
    private function seedMemorials(): void
    {
        $memorials = require database_path('seeders/demo-profiles-memorial.php');

        foreach ($memorials as $slug => $m) {
            $profile = InMemoriamProfile::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'deceased_full_name' => $m['name'],
                    // "(Late)" is the approved memorial presentation suffix, applied
                    // at the display-name layer only.
                    'deceased_display_name' => $m['display'],
                    'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
                    'profession' => $m['profession'],
                    'bio_headline' => $m['headline'],
                    'deceased_date_of_birth' => null,
                    'deceased_date_of_death' => null,
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

            // The corpus supplies no location for either memorial. Public
            // offices require a location, so the role is carried by the
            // headline alone.
            InMemoriamGeography::query()->where('in_memoriam_profile_id', $profile->id)->delete();
            InMemoriamPublicOffice::query()->where('in_memoriam_profile_id', $profile->id)->delete();

            foreach ([InMemoriamEditorialContent::LANGUAGE_EN => $m['en'], InMemoriamEditorialContent::LANGUAGE_ML => $m['ml']] as $language => $content) {
                InMemoriamEditorialContent::query()->updateOrCreate(
                    ['in_memoriam_profile_id' => $profile->id, 'language' => $language, 'version_number' => 1],
                    [
                        'title' => $content['title'],
                        'summary' => $content['summary'],
                        'body' => $content['body'],
                        'status' => InMemoriamEditorialContent::STATUS_APPROVED,
                        'ai_generated' => false,
                    ],
                );
            }

            $this->attachMemorialPortrait($profile, $m['portrait'], 'Portrait of '.$m['name']);

            $this->command?->info(sprintf('Demo memorial: %s (slug: %s).', $m['display'], $slug));
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
     * Unchanged portraits (same SHA-256) are left untouched so re-runs do
     * not rewrite storage keys under a published integrity snapshot.
     * Returns true when the stored primary portrait was created or replaced.
     * ----------------------------------------------------------------- */
    private function attachPortrait(Profile $profile, ?string $filename, string $alt): bool
    {
        if ($filename === null || $filename === '') {
            return false;
        }

        $source = database_path('seeders/demo-media/'.$filename);
        if (! is_file($source)) {
            $this->command?->warn("Portrait not bundled yet: {$filename} (skipping — placeholder will be shown).");

            return false;
        }

        $bytes = file_get_contents($source);
        if ($bytes === false) {
            return false;
        }

        if ($this->primaryPortraitHash($profile) === hash('sha256', $bytes)) {
            return false;
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

        return true;
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

        if ($this->primaryPortraitHash($profile) === hash('sha256', $bytes)) {
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

    private function primaryPortraitHash(Profile|InMemoriamProfile $owner): ?string
    {
        return MediaItem::query()
            ->where('mediable_type', $owner->getMorphClass())
            ->where('mediable_id', $owner->id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->where('is_primary', true)
            ->value('photo_sha256');
    }
}

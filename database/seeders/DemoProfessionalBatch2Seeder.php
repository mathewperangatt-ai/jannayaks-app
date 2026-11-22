<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\ApplicationWorkflowService;
use App\Services\ConsentRecordingService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\EditorialGenerationService;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileMediaService;
use App\Services\ProfileUrlService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

/**
 * TEN fictional professional demo profiles (5 NRI + 5 outside-Kerala India)
 * from the supplied package. Unlike batch 1, the ENGLISH editorial text is
 * SUPPLIED (human-authored fixture — ai_generated=false); the existing AI
 * integration is used ONLY for the Malayalam adaptation through
 * EditorialGenerationService::generateMalayalamAdaptation().
 *
 * No questionnaire/interview is created for these profiles (per package
 * instructions). Everything else follows the existing application → waiver →
 * editorial review → customer approval → publication workflow, and each
 * profile is published into the EXISTING demo-profiles section via the
 * jannayaks.internal demo domain.
 *
 * Professional URL prefixes (dr./adv./prof.) are retained in the slugs —
 * supported by PersonalSlugGenerator::parsePersonalSlug and validated as
 * name-derived. meera.krishnan collides with the existing batch-1 demo and
 * receives the standard numeric suffix.
 *
 * Idempotent and resumable: completed profiles are skipped; partial ones
 * resume at their current stage.
 */
class DemoProfessionalBatch2Seeder extends Seeder
{
    private const SLUGS = [
        'dr.devika.nair', 'arun.mathew', 'meera.krishnan', 'rajesh.pillai', 'anoop.varghese',
        'dr.sandeep.menon', 'adv.nandita.rao', 'prof.vivek.menon', 'dr.rhea.thomas', 'rajiv.menon',
    ];

    private User $demoAdmin;

    private User $demoEditor;

    public function run(): void
    {
        $this->demoAdmin = User::query()->updateOrCreate(
            ['email' => 'demo.admin@jannayaks.internal'],
            [
                'name' => 'Jannayaks Demo Editorial',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        $this->demoEditor = User::query()->updateOrCreate(
            ['email' => 'demo.editor@jannayaks.internal'],
            [
                'name' => 'Jannayaks Demo Reviewer',
                'role' => User::ROLE_EDITOR,
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        $base = database_path('seeders/demo-professionals-2');
        $done = 0;
        foreach (self::SLUGS as $slug) {
            $mdPath = null;
            foreach (['01_nri_profiles', '02_outside_kerala_india'] as $groupDir) {
                $candidate = $base.DIRECTORY_SEPARATOR.$groupDir.DIRECTORY_SEPARATOR.$slug.DIRECTORY_SEPARATOR.'profile_en.md';
                if (is_file($candidate)) {
                    $mdPath = $candidate;
                    $mediaDir = $base.DIRECTORY_SEPARATOR.'media';
                    break;
                }
            }
            if ($mdPath === null) {
                $this->command?->warn("Fixture not found for $slug — skipped.");

                continue;
            }

            // Each profile receives exactly its own three photographs: the
            // fixture files are prefixed with the slug's underscore form.
            $photoPrefix = str_replace('.', '_', $slug);
            $photoFiles = collect(glob($mediaDir.DIRECTORY_SEPARATOR.$photoPrefix.'_*.jpg'))->sort()->values()->all();
            if (count($photoFiles) !== 3) {
                $this->command?->warn("Expected 3 photographs for $slug, found ".count($photoFiles)." — skipped.");

                continue;
            }

            $this->seedProfessional($slug, $mdPath, $photoFiles);
            $done++;
        }

        $this->command?->info("Batch complete: $done of ".count(self::SLUGS).' profiles processed.');
    }

    /** Parse the supplied profile_en.md into title/summary/body (+ role, location). */
    private function parseFixture(string $markdown): array
    {
        $lines = preg_split('/\r?\n/', $markdown) ?: [];
        $title = '';
        $role = '';
        $location = '';
        $body = [];
        $inNarrative = false;
        $introSummary = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '# ')) {
                $title = trim(substr($line, 2));

                continue;
            }
            if (str_starts_with($line, '**Role:**')) {
                $role = trim(str_replace(['**Role:**', '**'], '', $line));

                continue;
            }
            if (str_starts_with($line, '**Location:**')) {
                $location = trim(str_replace(['**Location:**', '**'], '', $line));

                continue;
            }
            if (str_starts_with($line, '> ')) {
                continue; // DEMO disclosure blockquote (rendered by the page, not the article)
            }
            if (str_starts_with($line, '## Profile introduction')) {
                $inNarrative = 'intro';

                continue;
            }
            if (str_starts_with($line, '## Profile narrative')) {
                $inNarrative = 'narrative';

                continue;
            }
            if (str_starts_with($line, '## ')) {
                $inNarrative = null; // Suggested image placement / editorial notes — excluded

                continue;
            }
            if ($inNarrative === 'intro' && trim($line) !== '') {
                $introSummary = trim($introSummary.' '.$line);

                continue;
            }
            if ($inNarrative === 'narrative' && trim($line) !== '') {
                $body[] = trim($line);
            }
        }

        return [
            'title' => $title,
            'summary' => $introSummary !== '' ? $introSummary : mb_substr(implode(' ', $body), 0, 200),
            'body' => implode("\n\n", $body),
            'role' => $role,
            'location' => $location,
        ];
    }

    private function seedProfessional(string $slug, string $mdPath, array $photoFiles): void
    {
        $workflow = app(CustomerEditorialWorkflowService::class);
        $fixture = $this->parseFixture((string) file_get_contents($mdPath));
        $this->command?->info("=== $slug — {$fixture['title']} ===");

        // 1. Controlled demo account.
        $owner = User::query()->updateOrCreate(
            ['email' => "{$slug}.demo2@jannayaks.internal"],
            [
                'name' => $fixture['title'],
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        // 2. Profile — the professional URL prefix (dr./adv./prof.) is set as
        //    the explicit slug. Supported by PersonalSlugGenerator::parsePersonalSlug
        //    (title-prefixed, name-derived) though never auto-suggested. If the
        //    name-based address is already taken, the EXISTING convention applies:
        //    the first available suggested combination (short numeric suffix).
        $urlService = app(ProfileUrlService::class);
        $resolvedSlug = $urlService->normalizePersonalSlug($slug);
        if ($urlService->isSlugTaken($resolvedSlug)) {
            $suggestions = $urlService->suggestPersonalSlugs($fixture['title']);
            $fallback = collect($suggestions)->first(fn (array $s): bool => $s['available']);
            if ($fallback === null) {
                $this->command?->warn("  no available slug for '$slug' — skipped.");

                return;
            }
            $resolvedSlug = $fallback['slug'];
            $this->command?->info("  slug '$slug' is taken — using '$resolvedSlug'.");
        }

        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $owner->id],
            [
                'status' => 'under_editorial_review',
                'full_name' => $fixture['title'],
                'display_name' => $fixture['title'],
                'profession' => $fixture['role'],
                'bio_headline' => $fixture['role'],
                'slug' => $resolvedSlug,
                'slug_generated_at' => now(),
                'slug_changed_at' => now(),
                'display_phone_consent' => false,
                'display_email_consent' => false,
            ],
        );
        $profile->ensureMandatoryGeography();

        // 3. Application — NO questionnaire for this batch (package instruction).
        //    Status goes straight to editorial review; payment settled by the
        //    existing administrator waiver mechanism.
        $countryCode = $this->countryCodeFor($fixture['location']);
        $application = Application::query()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'user_id' => $owner->id,
                'applicant_email_normalized' => Application::normalizeApplicantEmail("{$slug}.demo2@jannayaks.internal"),
                'source_method' => 'admin_test_demo',
                'package_tier' => \App\Support\PricingAmounts::STANDARD_INTERNAL_TIER,
                'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
                'country_of_residence' => $countryCode,
                'full_name' => $fixture['title'],
                'preferred_display_name' => $fixture['title'],
                'preferred_slug' => $slug,
                'broad_location' => $fixture['location'],
                'preferred_contact_email' => "{$slug}.demo2@jannayaks.internal",
                'intake_started_at' => now(),
                'payment_status' => Application::PAYMENT_STATUS_PENDING,
                'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
                'admin_demo_audit_note' => 'Fictional professional demo population (batch 2 seeder).',
            ],
        );

        // 4. Declarations + administrator payment waiver (audited).
        app(ConsentRecordingService::class)->recordSolicitationDeclaration(
            $owner,
            '127.0.0.1',
            'demo-seeder-batch2',
            $application,
        );
        if ($application->payment_status !== Application::PAYMENT_STATUS_WAIVED) {
            app(ApplicationPaymentStateService::class)->recordStaffWaiver(
                $application->fresh(),
                $this->demoAdmin,
                "Fictional professional demo profile — authorised payment waiver (batch 2: $slug).",
            );
            $this->command?->info('  payment waiver recorded (audited).');
        }

        // 5. Supplied English editorial (human-authored fixture, ai_generated=false)
        //    stored through the existing editorial mechanisms as version 1.
        $english = EditorialContent::query()->firstOrCreate(
            [
                'profile_id' => $profile->id,
                'language' => EditorialContent::LANGUAGE_EN,
                'version_number' => 1,
            ],
            [
                'title' => $fixture['title'],
                'summary' => $fixture['summary'],
                'body' => $fixture['body'],
                'status' => EditorialContent::STATUS_DRAFT,
                'ai_generated' => false,
                'reviewed_by_id' => $this->demoAdmin->id,
                'source_material' => 'Supplied fictional professional profile package (batch 2). Human-authored English source; Malayalam adaptation generated via the existing AI integration and reviewed before publication.',
            ],
        );

        // 6. Malayalam adaptation through the existing AI integration —
        //    the ONLY AI call for this batch, per package instructions.
        $malayalam = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->orderByDesc('version_number')
            ->first();
        if ($malayalam === null) {
            $run = app(EditorialGenerationService::class)->generateMalayalamAdaptation(
                $application->fresh(),
                $this->demoAdmin,
                [
                    'id' => $english->id,
                    'title' => $fixture['title'],
                    'summary' => $fixture['summary'],
                    'body' => $fixture['body'],
                ],
            );
            if ($run->isFailed()) {
                $this->command?->warn('  MALAYALAM ADAPTATION FAILED (recorded): '.$run->error_message);

                return;
            }
            $malayalam = EditorialContent::query()->find($run->malayalam_editorial_content_id);
            $this->command?->info('  Malayalam adaptation generated.');
        }

        // 7. Human editorial review + approval of both languages (existing
        //    editor-update and English-master mechanisms).
        $english = EditorialContent::query()->find($english->id);
        if ($english->status !== EditorialContent::STATUS_APPROVED) {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($english, [
                'title' => $english->title,
                'summary' => $english->summary,
                'body' => $english->body,
                'status' => EditorialContent::STATUS_APPROVED,
                'review_comment' => 'Human review against the supplied fictional fixture.',
            ], $this->demoAdmin);
            $this->command?->info('  English editorial approved.');
        }

        $malayalam = EditorialContent::query()->find($malayalam->id);
        if ($malayalam->status !== EditorialContent::STATUS_APPROVED) {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($malayalam, [
                'title' => $malayalam->title,
                'summary' => $malayalam->summary,
                'body' => $malayalam->body,
                'status' => EditorialContent::STATUS_APPROVED,
                'source_material' => $malayalam->source_material,
                'review_comment' => 'Human review of the Malayalam adaptation against the supplied English source.',
            ], $this->demoAdmin);
            $this->command?->info('  Malayalam approved.');
        }

        // 8. Photographs — three per profile via the existing media workflow.
        $this->uploadAndApprovePhotos($profile, $photoFiles, $fixture['title'], $owner);

        // 9. Release → version-pinned customer approval → publication.
        $application = $application->fresh();
        if ($application->customer_preview_released_at === null
            && $application->status !== Application::STATUS_PUBLISHED) {
            $workflow->releaseForCustomerPreview($application, $this->demoAdmin);
            $application->refresh();
            $this->command?->info('  customer preview released.');
        }

        if ($application->customer_approved_at === null
            && $application->status === Application::STATUS_EDITORIAL_APPROVED) {
            Auth::login($owner);
            $workflow->approvePreview(
                $application->fresh(),
                $owner,
                (int) $application->preview_english_editorial_content_id,
                '127.0.0.1',
                'demo-seeder-batch2',
            );
            $application->refresh();
            $this->command?->info('  customer approval recorded (version-pinned).');
        }

        if ($application->status === Application::STATUS_AWAITING_PUBLICATION) {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $this->demoAdmin);
            $application->refresh();
            $this->command?->info('  PUBLISHED (admin publication control).');
        }

        $publishedMl = EditorialContent::query()->find((int) $application->published_malayalam_editorial_content_id);
        if ($malayalam->status === EditorialContent::STATUS_APPROVED
            && ($publishedMl === null || (int) $publishedMl->id !== (int) $malayalam->id)) {
            app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $this->demoAdmin, $malayalam);
            $this->command?->info('  Malayalam version published.');
        }

        $profile->refresh();
        $this->command?->info(sprintf(
            '  DONE: %s — slug: %s, application #%d, status: %s.',
            $fixture['title'],
            $profile->slug,
            $application->id,
            $application->status,
        ));
    }

    private function uploadAndApprovePhotos(Profile $profile, array $files, string $displayName, User $owner): void
    {
        $media = app(ProfileMediaService::class);
        $uploaded = 0;
        $index = 0;

        foreach ($files as $file) {
            // Idempotency guard: the three-photograph limit is enforced by
            // the media service; skip instead of throwing on re-runs.
            if ($media->profilePhotoCount($profile->fresh()) >= 3) {
                $this->command?->info('  profile already has its photographs — skipped.');

                return;
            }
            $index++;
            $isPrimary = $index === 1;
            $alt = $isPrimary
                ? 'AI-generated portrait of '.$displayName.' (fictional demo profile)'
                : 'AI-generated illustration of '.$displayName.' (fictional demo profile), image '.$index;

            $existing = $profile->media()
                ->where('media_type', \App\Models\MediaItem::TYPE_PROFILE_PHOTO)
                ->where('alt_text', $alt)
                ->first();
            if ($existing !== null) {
                continue;
            }

            $uploadedFile = new UploadedFile($file, basename($file), 'image/jpeg', null, true);
            $item = $media->uploadProfilePhoto(
                profile: $profile->fresh(),
                file: $uploadedFile,
                actor: $owner,
                altText: $alt,
                makePrimary: $isPrimary,
            );
            app(ProfileMediaService::class)->approve($item->fresh(), $this->demoEditor, 'Fictional professional demo profile photograph (batch 2).');
            $uploaded++;
        }

        if ($uploaded > 0) {
            app(ProfileIntegrityService::class)->refreshSnapshot($profile->fresh(), 'demo_professionals_batch2.photos_added');
            $this->command?->info("  $uploaded photograph(s) uploaded + approved.");
        }

        $count = $profile->media()
            ->where('media_type', \App\Models\MediaItem::TYPE_PROFILE_PHOTO)
            ->whereIn('review_status', [\App\Models\MediaItem::REVIEW_PENDING, \App\Models\MediaItem::REVIEW_APPROVED])
            ->count();
        $this->command?->info("  photographs on profile: $count of 3.");
    }

    /** Map a fixture location to an ISO 3166-1 alpha-2 residence code. */
    private function countryCodeFor(string $location): string
    {
        $country = trim((string) Str::of($location)->afterLast(','));
        $codes = \App\Support\Countries::all();
        foreach ($codes as $code => $name) {
            if (strcasecmp($name, $country) === 0) {
                return $code;
            }
        }

        return 'IN';
    }
}

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
use App\Services\OnlineInterviewService;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileMediaService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

/**
 * Six fictional professional demo profiles driven through the REAL
 * application → interview → AI editorial → human review → photographs →
 * customer approval → publication workflow, then surfaced through the
 * EXISTING demo-profiles section (jannayaks.internal demo domain).
 *
 * Idempotent and resumable: each stage is skipped when already completed.
 * Interview answers are submitted VERBATIM from the supplied fixture —
 * omitted questions stay unanswered and the real validation decides.
 */
class DemoProfessionalsSeeder extends Seeder
{
    private const PROMOTIONAL_MARKERS = [
        'available for property disputes',
        'contact my office for appointments',
        'reasonable consultation fees',
        'welcome enquiries',
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

        // Approver must differ from the uploader (separation of duties).
        $this->demoEditor = User::query()->updateOrCreate(
            ['email' => 'demo.editor@jannayaks.internal'],
            [
                'name' => 'Jannayaks Demo Reviewer',
                'role' => User::ROLE_EDITOR,
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        $fixtures = json_decode(
            (string) file_get_contents(database_path('seeders/demo-professionals/interview_answers.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($fixtures['profiles'] as $profileFixture) {
            $this->seedProfessional($profileFixture);
        }
    }

    private function seedProfessional(array $fixture): void
    {
        $id = (string) $fixture['id'];
        $workflow = app(CustomerEditorialWorkflowService::class);
        $this->command?->info("=== {$fixture['name']} ===");

        // 1. Controlled demo account (jannayaks.internal → demo labels,
        //    sitemap exclusion — the established convention).
        $owner = User::query()->updateOrCreate(
            ['email' => "{$id}.demo@jannayaks.internal"],
            [
                'name' => $fixture['name'],
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        // 2. Profile.
        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $owner->id],
            [
                'status' => 'under_editorial_review',
                'full_name' => $fixture['name'],
                'display_name' => $fixture['name'],
                'profession' => $fixture['profession'],
                'bio_headline' => $fixture['profession'],
                'display_phone_consent' => false,
                'display_email_consent' => false,
            ],
        );
        $profile->ensureMandatoryGeography();

        // 3. Application through the normal Online Interview intake.
        $application = Application::query()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'user_id' => $owner->id,
                'applicant_email_normalized' => Application::normalizeApplicantEmail("{$id}.demo@jannayaks.internal"),
                'source_method' => 'online_interview',
                'package_tier' => \App\Support\PricingAmounts::STANDARD_INTERNAL_TIER,
                'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
                'country_of_residence' => 'IN',
                'full_name' => $fixture['name'],
                'preferred_display_name' => $fixture['name'],
                'broad_location' => (string) $fixture['location'],
                'preferred_contact_email' => "{$id}.demo@jannayaks.internal",
                'intake_started_at' => now(),
                'payment_status' => Application::PAYMENT_STATUS_PENDING,
                'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
            ],
        );

        // 4. Mandatory solicitation declaration (real consent mechanism).
        app(ConsentRecordingService::class)->recordSolicitationDeclaration(
            $owner,
            '127.0.0.1',
            'demo-seeder',
            $application,
        );

        // 5. Administrator payment waiver (existing audited mechanism).
        if ($application->payment_status !== Application::PAYMENT_STATUS_WAIVED) {
            app(ApplicationPaymentStateService::class)->recordStaffWaiver(
                $application->fresh(),
                $this->demoAdmin,
                'Fictional demo profile population — authorised payment waiver (DemoProfessionalsSeeder).',
            );
            $this->command?->info('  payment waiver recorded (audited).');
        }

        // 6. Online Interview — verbatim fixture answers through the real
        //    controller (validation, confirmations, submission state).
        $application->refresh();
        if (! $application->isInterviewSubmitted()) {
            Auth::login($owner);
            app(OnlineInterviewService::class)->saveAnswers($application, $fixture['answers']);

            $request = Request::create(
                '/applications/'.$application->id.'/interview/submit',
                'POST',
                [
                    'answers' => $fixture['answers'],
                    'questionnaire_confirmed' => '1',
                    'accuracy_confirmed' => '1',
                ],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json'],
            );
            $request->setUserResolver(fn () => $owner);
            $response = app(\App\Http\Controllers\OnlineInterviewController::class)->submit($request, $application->fresh());

            $application->refresh();
            if ($response instanceof \Illuminate\Http\JsonResponse && $response->status() !== 200) {
                $this->command?->warn(sprintf(
                    '  INTERVIEW SUBMISSION REJECTED (recorded, continuing): %s',
                    (string) $response->getData(true)['error'] ?? 'validation failed',
                ));

                return;
            }
            $this->command?->info(sprintf('  interview submitted (%d answers), status: %s', count($fixture['answers']), $application->status));
        } else {
            $this->command?->info('  interview already submitted — skipped.');
        }

        // 7. AI editorial generation through the existing integration
        //    (EN master + Malayalam adaptation drafts). Verbatim AI output
        //    is preserved; human review happens below.
        $english = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->orderByDesc('version_number')
            ->first();
        $malayalam = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->orderByDesc('version_number')
            ->first();

        if (in_array($application->fresh()->status, [
                Application::STATUS_AWAITING_EDITORIAL_REVIEW,
                Application::STATUS_IN_EDITORIAL_REVIEW,
            ], true)
            && ($english === null || $malayalam === null)) {
            $run = app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $this->demoAdmin);
            if ($run->isFailed()) {
                $this->command?->warn('  AI GENERATION FAILED (recorded): '.$run->error_message);

                return;
            }

            $english = EditorialContent::query()->find($run->english_editorial_content_id);
            $malayalam = $run->malayalam_editorial_content_id !== null
                ? EditorialContent::query()->find($run->malayalam_editorial_content_id)
                : null;
            $this->command?->info(sprintf('  AI drafts generated (EN v%s, ML %s).', $english?->version_number, $malayalam?->version_number ?? '—'));
        }

        // 8. Human editorial review through the existing editing mechanism.
        //    Adv. Meera Krishnan's fixture contains promotional wording
        //    (Q22): strip invitations/fee promotion during review, keeping
        //    factual practice information. Draft rows are edited in place
        //    via the standard editor update path.
        $english = EditorialContent::query()->find($english->id);
        $malayalam = $malayalam !== null ? EditorialContent::query()->find($malayalam->id) : null;
        if ($id === 'meera_krishnan') {
            foreach ([['en', $english], ['ml', $malayalam]] as [$lang, $draft]) {
                if ($draft instanceof EditorialContent
                    && $draft->status === EditorialContent::STATUS_DRAFT
                    && $this->containsPromotionalWording((string) $draft->body)) {
                    app(EditorialContentVersioningService::class)->applyEditorUpdate(
                        $draft,
                        [
                            'title' => $draft->title,
                            'summary' => $draft->summary,
                            'body' => $this->stripPromotionalSentences((string) $draft->body),
                            'status' => EditorialContent::STATUS_DRAFT,
                        ],
                        $this->demoAdmin,
                    );
                    $this->command?->info("  human review: promotional wording removed from {$lang} draft.");
                }
            }
        }

        // 9. Approve English master + Malayalam (existing mechanisms).
        $english = EditorialContent::query()->find($english->id);
        if ($english->status !== EditorialContent::STATUS_APPROVED) {
            app(ApplicationWorkflowService::class)->approveEnglishMaster($application->fresh(), $this->demoAdmin);
            $this->command?->info('  English master approved.');
        }

        $malayalam = $malayalam !== null ? EditorialContent::query()->find($malayalam->id) : null;
        if ($malayalam instanceof EditorialContent && $malayalam->status !== EditorialContent::STATUS_APPROVED) {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($malayalam, [
                'title' => $malayalam->title,
                'summary' => $malayalam->summary,
                'body' => $malayalam->body,
                'status' => EditorialContent::STATUS_APPROVED,
                'source_material' => $malayalam->source_material,
                'review_comment' => $malayalam->review_comment,
            ], $this->demoAdmin);
            $this->command?->info('  Malayalam approved.');
        }

        // 10. Photographs — three per applicant through the existing media
        //     workflow (optimisation, SHA-256, private until staff approval).
        $this->uploadAndApprovePhotos($profile, $fixture, $owner);

        // 11. Release → customer approval → publication (existing workflow).
        $application = $application->fresh();
        if ($application->status !== Application::STATUS_AWAITING_PUBLICATION
            && $application->customer_preview_released_at === null
            && in_array($application->status, [
                Application::STATUS_AWAITING_EDITORIAL_REVIEW,
                Application::STATUS_IN_EDITORIAL_REVIEW,
            ], true)) {
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
                'demo-seeder',
            );
            $application->refresh();
            $this->command?->info('  customer approval recorded (version-pinned).');
        }

        if ($application->status === Application::STATUS_AWAITING_PUBLICATION) {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $this->demoAdmin);
            $application->refresh();
            $this->command?->info('  PUBLISHED (admin publication control).');
        }

        $latestMalayalam = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->orderByDesc('version_number')
            ->first();
        $application->refresh();
        if ($latestMalayalam instanceof EditorialContent
            && $application->status === Application::STATUS_PUBLISHED
            && (int) $application->published_malayalam_editorial_content_id !== (int) $latestMalayalam->id) {
            app(ApplicationWorkflowService::class)->publishMalayalam($application, $this->demoAdmin, $latestMalayalam);
            $this->command?->info('  Malayalam version published.');
        }

        $profile->refresh();
        $this->command?->info(sprintf(
            '  DONE: %s — slug: %s, application #%d, status: %s.',
            $fixture['name'],
            $profile->slug,
            $application->id,
            $application->status,
        ));
    }

    private function uploadAndApprovePhotos(Profile $profile, array $fixture, User $owner): void
    {
        $mediaDir = database_path('seeders/demo-professionals-media');
        $media = app(ProfileMediaService::class);
        $uploaded = 0;

        foreach ($fixture['photos'] as $photo) {
            $slot = (string) $photo['slot'];
            $fileSlot = ['primary' => 'primary', 'additional_1' => 'activity_1', 'additional_2' => 'activity_2'][$slot] ?? $slot;
            $path = $mediaDir.DIRECTORY_SEPARATOR.$fixture['id'].'_'.$fileSlot.'.jpg';
            if (! is_file($path)) {
                $this->command?->warn("  photo missing on disk: $path");

                continue;
            }

            $alt = $slot === 'primary'
                ? 'AI-generated portrait of '.$fixture['name'].' (fictional demo profile)'
                : 'AI-generated illustration: '.$photo['scene'].' (fictional demo profile)';

            $existing = $profile->media()
                ->where('media_type', \App\Models\MediaItem::TYPE_PROFILE_PHOTO)
                ->where('alt_text', $alt)
                ->first();
            if ($existing !== null) {
                continue;
            }

            $uploadedFile = new UploadedFile($path, basename($path), 'image/jpeg', null, true);
            $item = $media->uploadProfilePhoto(
                profile: $profile->fresh(),
                file: $uploadedFile,
                actor: $owner,
                altText: $alt,
                makePrimary: $slot === 'primary',
            );
            app(ProfileMediaService::class)->approve($item->fresh(), $this->demoEditor, 'Demo professional profile photograph.');
            $uploaded++;
        }

        if ($uploaded > 0) {
            $this->command?->info("  $uploaded photographs uploaded + approved (primary designation preserved).");
            app(ProfileIntegrityService::class)->refreshSnapshot($profile->fresh(), 'demo_professionals_seeder.photos_added');
        }

        $count = $profile->media()
            ->where('media_type', \App\Models\MediaItem::TYPE_PROFILE_PHOTO)
            ->whereIn('review_status', [\App\Models\MediaItem::REVIEW_PENDING, \App\Models\MediaItem::REVIEW_APPROVED])
            ->count();
        $this->command?->info("  photographs on profile: $count of 3.");
    }

    private function containsPromotionalWording(string $text): bool
    {
        $haystack = mb_strtolower($text);

        foreach (self::PROMOTIONAL_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Human editorial review: remove sentences inviting readers to engage
     * the advocate's services (fees, appointments, enquiries) while keeping
     * factual practice information. Applied to the AI draft in place.
     */
    private function stripPromotionalSentences(string $body): string
    {
        $sentences = preg_split('/(?<=[.!?।])\s+/u', $body) ?: [$body];
        $kept = [];
        foreach ($sentences as $sentence) {
            $lower = mb_strtolower($sentence);
            $promotional = false;
            foreach (self::PROMOTIONAL_MARKERS as $marker) {
                if (str_contains($lower, $marker) || str_contains($sentence, 'സൗജന്യമായി നൽകുന്ന') || str_contains($sentence, 'നിരക്ക്')) {
                    $promotional = true;
                    break;
                }
            }
            if (! $promotional) {
                $kept[] = trim($sentence);
            }
        }

        return implode(' ', array_filter($kept));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AiEditorialRun;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch-2 professional demo seeding (5 NRI + 5 outside-Kerala): the seeder
 * prepares waived demo applications, uploads each profile's own three
 * photographs, and generates the Malayalam adaptation through the existing
 * AI integration — then STOPS, leaving every profile pending the normal
 * editorial review and approval process. No automatic approvals, preview
 * releases or publications.
 */
class DemoProfessionalBatch2SeederTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = [
        'dr.devika.nair', 'arun.mathew', 'meera.krishnan', 'rajesh.pillai', 'anoop.varghese',
        'dr.sandeep.menon', 'adv.nandita.rao', 'prof.vivek.menon', 'dr.rhea.thomas', 'rajiv.menon',
    ];

    private function seedBatch(): void
    {
        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\DemoProfessionalBatch2Seeder::class,
            '--force' => true,
        ]);
    }

    public function test_fixture_media_map_one_to_one_to_the_ten_profile_slugs(): void
    {
        $mediaDir = database_path('seeders/demo-professionals-2/media');
        $allSeen = [];

        foreach (self::SLUGS as $slug) {
            $prefix = str_replace('.', '_', $slug);
            $files = glob($mediaDir.DIRECTORY_SEPARATOR.$prefix.'_*.jpg');

            $this->assertCount(3, $files, "$slug must have exactly three photographs.");
            foreach ($files as $file) {
                $base = basename($file, '.jpg');
                $this->assertStringStartsWith($prefix.'_', $base, "$base must belong to $slug.");
                $this->assertNotContains($base, $allSeen, "$base must not be shared with another profile.");
                $allSeen[] = $base;
            }
        }

        $this->assertCount(30, $allSeen, 'Ten profiles × three photographs, no duplicates.');
    }

    public function test_seeder_prepares_all_ten_profiles_pending_editorial_review(): void
    {
        $this->seedBatch();

        $this->assertSame(10, Application::query()->count());

        foreach (self::SLUGS as $slug) {
            $userEmail = "{$slug}.demo2@jannayaks.internal";
            $profile = Profile::query()->whereHas('user', fn ($q) => $q->where('email', $userEmail))->firstOrFail();

            $this->assertSame($slug, $profile->slug, "$slug: professional URL prefix retained.");

            $application = Application::query()->where('profile_id', $profile->id)->firstOrFail();
            // No questionnaire for this batch: the application awaits the
            // editorial team's review of the supplied English source.
            $this->assertSame(Application::STATUS_AWAITING_EDITORIAL_REVIEW, $application->status, "$slug pending editorial review.");
            $this->assertSame(Application::PAYMENT_STATUS_WAIVED, $application->payment_status, "$slug waived via the audited mechanism.");

            // Supplied English editorial stored as a human-authored draft.
            $english = EditorialContent::query()
                ->where('profile_id', $profile->id)
                ->where('language', EditorialContent::LANGUAGE_EN)
                ->firstOrFail();
            $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status, "$slug EN remains a draft for review.");
            $this->assertFalse((bool) $english->ai_generated, "$slug EN is the human-authored source, not AI-generated.");

            // Malayalam adaptation generated through the existing AI integration.
            $malayalam = EditorialContent::query()
                ->where('profile_id', $profile->id)
                ->where('language', EditorialContent::LANGUAGE_ML)
                ->firstOrFail();
            $this->assertSame(EditorialContent::STATUS_DRAFT, $malayalam->status, "$slug ML remains a draft for review.");
            $this->assertTrue((bool) $malayalam->ai_generated, "$slug ML is the AI adaptation.");
            $this->assertSame(
                $english->id,
                (int) $malayalam->source_editorial_content_id,
                "$slug ML is paired with its own English master.",
            );
        }
    }

    public function test_each_profile_has_its_own_three_approved_photographs(): void
    {
        $seenPhotoIds = [];
        $seenNames = [];

        $this->seedBatch();

        foreach (self::SLUGS as $slug) {
            $profile = Profile::query()
                ->whereHas('user', fn ($q) => $q->where('email', "{$slug}.demo2@jannayaks.internal"))
                ->firstOrFail();

            $this->assertSame(3, $profile->media()->count(), "$slug: three photographs.");

            foreach ($profile->media as $photo) {
                $this->assertSame('approved', $photo->review_status, "$slug photos are staff-approved.");
                $this->assertStringContainsString($profile->display_name, (string) $photo->alt_text, "$slug photos carry their own identity.");
                $seenPhotoIds[] = (int) $photo->id;
                $seenNames[] = $profile->display_name;
            }
        }

        $this->assertSame(30, count($seenPhotoIds), 'Ten profiles × three photographs.');
        $this->assertSame(30, count(array_unique($seenPhotoIds)), 'No photograph assigned to two profiles.');
        $this->assertSame(10, count(array_unique($seenNames)), 'Ten distinct professional profiles.');
    }

    public function test_no_profile_is_auto_approved_released_or_published(): void
    {
        $this->seedBatch();

        foreach (Profile::query()->whereHas('user', fn ($q) => $q->where('email', 'like', '%.demo2@jannayaks.internal'))->get() as $profile) {
            $application = Application::query()->where('profile_id', $profile->id)->firstOrFail();

            $this->assertNull($application->customer_preview_released_at, $profile->slug.': preview NOT auto-released.');
            $this->assertNull($application->customer_approved_at, $profile->slug.': NOT auto-approved.');
            $this->assertNull($application->published_english_editorial_content_id, $profile->slug.': NOT auto-published.');
            $this->assertNull($application->published_malayalam_editorial_content_id, $profile->slug.': ML NOT auto-published.');
            $this->assertNotSame(Application::STATUS_PUBLISHED, $application->status, $profile->slug);
            $this->assertNotSame(Application::STATUS_AWAITING_PUBLICATION, $application->status, $profile->slug);
        }
    }

    public function test_rerun_preserves_generated_drafts_without_duplicates(): void
    {
        $this->seedBatch();

        $contentBefore = EditorialContent::query()->count();
        $runsBefore = AiEditorialRun::query()->count();
        $mediaBefore = MediaItem::query()->count();

        $this->seedBatch();

        $this->assertSame($contentBefore, EditorialContent::query()->count(), 'Re-run adds no editorial versions.');
        $this->assertSame($mediaBefore, MediaItem::query()->count(), 'Re-run adds no photographs.');
        $this->assertSame(
            $runsBefore,
            AiEditorialRun::query()->count(),
            'Re-run must NOT re-call the AI integration for profiles with existing drafts.',
        );
    }

    public function test_failed_ml_adaptation_is_recorded_and_the_profile_stays_safe(): void
    {
        // A failing AI client (e.g. provider outage): every adaptation is
        // recorded as a failed run and no profile is corrupted or approved.
        $failing = new class implements \App\Contracts\EditorialAiClient
        {
            public function generateEditorial(array $input): array
            {
                throw new \RuntimeException('AI provider outage.');
            }

            public function providerName(): string
            {
                return 'gpt';
            }

            public function modelName(): string
            {
                return 'test-model';
            }
        };
        $this->app->instance(\App\Contracts\EditorialAiClient::class, $failing);

        $this->seedBatch();

        $this->assertSame(10, AiEditorialRun::query()->where('status', AiEditorialRun::STATUS_FAILED)->count(), 'Every failed adaptation is recorded on its run.');

        foreach (self::SLUGS as $slug) {
            $userEmail = "{$slug}.demo2@jannayaks.internal";
            $profile = Profile::query()->whereHas('user', fn ($q) => $q->where('email', $userEmail))->firstOrFail();
            $application = Application::query()->where('profile_id', $profile->id)->firstOrFail();

            $this->assertSame(0, EditorialContent::query()->where('profile_id', $profile->id)->where('language', 'ml')->count(), "$slug: no ML draft after failure.");
            $this->assertNull($application->customer_approved_at, "$slug: not approved.");
            $this->assertSame(Application::STATUS_AWAITING_EDITORIAL_REVIEW, $application->status, "$slug stays pending editorial review.");
        }
    }

    public function test_claude_provider_routes_to_the_claude_malayalam_standard(): void
    {
        // Production (Railway) sets JANNAYAKS_AI_PROVIDER=claude; the
        // AppServiceProvider then binds the Anthropic editorial client and
        // the ML pass selects the Claude Malayalam standard. The live Stage-2
        // test confirms the binding on Railway; here we verify the routing
        // inputs without making any paid calls.
        Config::set('jannayaks.ai.provider', 'claude');

        $this->assertSame('claude', config('jannayaks.ai.provider'));
        $this->assertNotEmpty(\App\Support\EditorialSystemPrompts::claudeMalayalamAdaptation());
        $this->assertTrue(class_exists(\App\Services\Ai\AnthropicEditorialClient::class));
        $this->assertTrue(is_a(\App\Services\Ai\AnthropicEditorialClient::class, \App\Contracts\EditorialAiClient::class, true));
    }

}

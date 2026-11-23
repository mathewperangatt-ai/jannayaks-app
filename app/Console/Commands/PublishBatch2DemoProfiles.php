<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operational command for the batch-2 fictional demo profiles.
 *
 * Takes the nine successfully generated batch-2 applications through the
 * EXISTING editorial approval and publication workflow (editor approval of
 * both languages, customer preview release, version-pinned customer
 * approval, admin publication, Malayalam publication). No AI calls, no
 * content rewrites, no direct database manipulation — every state change
 * goes through the same application services the normal workflow uses.
 *
 * Safety rails:
 *  - Only applications whose applicant email is a *.demo2@jannayaks.internal
 *    demo account are touched.
 *  - Only applications that already hold a Malayalam adaptation are
 *    touched (the one failed generation is structurally excluded).
 *  - Every step is guarded and idempotent; already-published profiles are
 *    reported and skipped.
 */
class PublishBatch2DemoProfiles extends Command
{
    protected $signature = 'demo:publish-batch2
        {--applications=30,31,32,34,35,36,37,38,39 : Batch-2 application IDs to publish}';

    protected $description = 'Take the nine generated batch-2 demo profiles through editorial approval and publication (no AI calls).';

    public function handle(): int
    {
        $admin = User::query()->where('email', 'demo.admin@jannayaks.internal')->first();
        if ($admin === null) {
            $this->error('demo.admin@jannayaks.internal not found — run the batch-2 seeder first.');

            return self::FAILURE;
        }

        $ids = array_map('intval', explode(',', (string) $this->option('applications')));
        $this->info('Target applications: '.implode(', ', $ids));

        foreach ($ids as $id) {
            $application = Application::query()->find($id);
            if ($application === null) {
                $this->warn("[$id] application not found — skipped.");

                continue;
            }
            if (! str_ends_with((string) $application->applicant_email_normalized, '.demo2@jannayaks.internal')) {
                $this->warn("[$id] not a batch-2 demo application — skipped.");

                continue;
            }

            try {
                $this->publishOne($application->fresh(), $admin);
            } catch (Throwable $e) {
                $this->error("[$id] FAILED: ".$e->getMessage());
            }
        }

        $this->info('AI run records for these profiles:');
        $rows = DB::table('ai_editorial_runs')
            ->whereIn('application_id', $ids)
            ->orderBy('id')
            ->get(['id', 'application_id', 'provider', 'model', 'stage', 'status', 'created_at']);
        foreach ($rows as $r) {
            $this->line("  run {$r->id} app={$r->application_id} {$r->provider} {$r->model} stage={$r->stage} {$r->status} {$r->created_at}");
        }

        return self::SUCCESS;
    }

    private function publishOne(Application $application, User $admin): void
    {
        $profile = $application->profile;
        $owner = User::query()->find($application->user_id);

        $en = EditorialContent::query()
            ->where('profile_id', $profile->id)->where('language', EditorialContent::LANGUAGE_EN)
            ->orderByDesc('version_number')->first();
        $ml = EditorialContent::query()
            ->where('profile_id', $profile->id)->where('language', EditorialContent::LANGUAGE_ML)
            ->orderByDesc('version_number')->first();

        if ($en === null || $ml === null) {
            $this->warn("[{$application->id}] missing EN or ML editorial — skipped (nothing fabricated).");

            return;
        }

        $photos = $profile->media()->where('media_type', 'profile_photo')->count();
        $this->line(sprintf(
            '[%d] %s BEFORE: status=%s EN v%d(%s,%s) ML v%d(%s,%s) photos=%d',
            $application->id,
            $profile->slug,
            $application->status,
            $en->version_number,
            $en->status,
            $en->ai_generated ? 'ai' : 'human',
            $ml->version_number,
            $ml->status,
            $ml->ai_generated ? 'ai' : 'human',
            $photos,
        ));
        $this->line(sprintf(
            '[%d] EN body integrity: len=%d sha=%s',
            $application->id,
            strlen($en->body),
            substr(sha1($en->body), 0, 12),
        ));

        // 1. Editor approval of the English master (existing editor-update mechanism).
        if ($en->status !== EditorialContent::STATUS_APPROVED) {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($en, [
                'title' => $en->title,
                'summary' => $en->summary,
                'body' => $en->body,
                'status' => EditorialContent::STATUS_APPROVED,
                'review_comment' => 'Human editorial review of the supplied batch-2 fictional demo biography.',
            ], $admin);
            $en = $en->fresh();
            $this->line("[{$application->id}] English editorial approved (v{$en->version_number}).");
        }

        // 2. Editor approval of the Malayalam adaptation.
        if ($ml->status !== EditorialContent::STATUS_APPROVED) {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($ml, [
                'title' => $ml->title,
                'summary' => $ml->summary,
                'body' => $ml->body,
                'source_material' => $ml->source_material,
                'status' => EditorialContent::STATUS_APPROVED,
                'review_comment' => 'Human editorial review of the Malayalam adaptation (batch-2 fictional demo).',
            ], $admin);
            $ml = $ml->fresh();
            $this->line("[{$application->id}] Malayalam editorial approved (v{$ml->version_number}).");
        }

        $application = $application->fresh();

        // 3. Customer preview release (existing workflow control).
        if ($application->customer_preview_released_at === null
            && $application->status !== Application::STATUS_PUBLISHED) {
            app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);
            $application = $application->fresh();
            $this->line("[{$application->id}] customer preview released.");
        }

        // 4. Version-pinned customer approval by the profile owner (demo customer).
        if ($application->customer_approved_at === null
            && $application->status === Application::STATUS_EDITORIAL_APPROVED) {
            app(CustomerEditorialWorkflowService::class)->approvePreview(
                $application->fresh(),
                $owner,
                (int) $application->preview_english_editorial_content_id,
                '127.0.0.1',
                'demo-batch2-publish-command',
            );
            $application = $application->fresh();
            $this->line("[{$application->id}] customer approval recorded (version-pinned).");
        }

        // 5. Admin publication control.
        if ($application->status === Application::STATUS_AWAITING_PUBLICATION) {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
            $application = $application->fresh();
            $this->line("[{$application->id}] PUBLISHED (admin publication control).");
        }

        // 6. Malayalam publication, paired to the published English version.
        $publishedMlId = $application->published_malayalam_editorial_content_id;
        if ($application->status === Application::STATUS_PUBLISHED
            && (int) $publishedMlId !== (int) $ml->id) {
            app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $ml);
            $application = $application->fresh();
            $this->line("[{$application->id}] Malayalam version published.");
        }

        $this->line(sprintf(
            '[%d] AFTER: status=%s published_en=%s published_ml=%s approved_at=%s',
            $application->id,
            $application->status,
            $application->published_english_editorial_content_id,
            $application->published_malayalam_editorial_content_id,
            $application->customer_approved_at?->toIso8601String(),
        ));
    }
}

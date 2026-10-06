<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\EditorialRevisionRequest;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\EditorialContentVersioningService;
use App\Services\EditorialGenerationService;
use App\Services\PostPublicationUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pass 2 — post-publication editorial processing, customer approval and
 * publication for maintenance requests. The application remains published
 * throughout; the maintenance lifecycle lives on the request record.
 */
class PostPublicationMaintenanceProcessingTest extends TestCase
{
    use RefreshDatabase;

    private PostPublicationUpdateService $maintenance;

    private User $admin;

    private User $member;

    private Application $application;

    private EditorialContent $publishedEn;

    private EditorialContent $publishedMl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenance = app(PostPublicationUpdateService::class);
        $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $this->member = User::factory()->create(['email_verified_at' => now()]);

        $profile = Profile::query()->create([
            'user_id' => $this->member->id,
            'status' => 'published',
            'full_name' => 'Arun Kumar Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $profile->forceFill([
            'slug' => 'arun.kumar.nair',
            'published_at' => '2026-06-01',
            'slug_generated_at' => now(),
        ])->save();

        $this->publishedEn = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'version_number' => 1,
            'status' => 'approved',
            'title' => 'Arun Kumar Nair',
            'summary' => 'Public life summary.',
            'body' => "He served as Director of ABC Foundation from 2019 to 2024.\n\nHe was known for civic dialogue.",
        ]);
        $this->publishedMl = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'ml',
            'version_number' => 1,
            'status' => 'approved',
            'title' => 'അരുൺ കുമാർ നായർ',
            'summary' => 'പൊതുജീവിത സംഗ്രഹം.',
            'body' => 'അദ്ദേഹം 2019 മുതൽ 2024 വരെ ഡയറക്ടറായി സേവനമനുഷ്ഠിച്ചു.',
        ]);

        $this->application = Application::factory()->paid()->create([
            'user_id' => $this->member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);
        $this->application->forceFill([
            'status' => Application::STATUS_PUBLISHED,
            'published_english_editorial_content_id' => $this->publishedEn->id,
            'published_malayalam_editorial_content_id' => $this->publishedMl->id,
        ])->save();

        $this->maintenance->submit(
            $this->application,
            $this->member,
            'Please change Director to Chairman from 2025, and update the record to reflect my 2026 board appointment.',
        );
    }

    private function openRequest(): EditorialRevisionRequest
    {
        return EditorialRevisionRequest::query()
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->orderByDesc('id')
            ->sole();
    }

    private function prepareAiDraft(): void
    {
        $this->maintenance->prepareViaAiDraft($this->application, $this->admin);
    }

    private function editorApprovesDraft(string $revisedBody): EditorialContent
    {
        $draft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);

        $prepared = app(EditorialContentVersioningService::class)->applyEditorUpdate($draft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'title' => $draft->title,
            'summary' => $draft->summary,
            'body' => $revisedBody,
        ], $this->admin);

        // The editor approves the paired Malayalam draft in the same pass
        // (bilingual profile) — pairing was established at creation.
        $mlId = $this->openRequest()->resulting_malayalam_editorial_content_id;
        if ($mlId !== null) {
            $ml = EditorialContent::query()->findOrFail($mlId);
            if ($ml->status !== EditorialContent::STATUS_APPROVED) {
                app(EditorialContentVersioningService::class)->applyEditorUpdate($ml, [
                    'status' => EditorialContent::STATUS_APPROVED,
                ], $this->admin, $prepared->id);
            }
        }

        return $prepared;
    }

    public function test_maintenance_request_enters_editorial_processing_with_preserved_ai_draft(): void
    {
        $before = $this->openRequest()->toArray();
        $this->prepareAiDraft();

        $request = $this->openRequest();
        $this->assertSame(EditorialRevisionRequest::STATUS_IN_PROGRESS, $request->status);
        $this->assertNotNull($request->resulting_english_editorial_content_id);

        // The AI draft exists, is a draft, is marked AI-generated, and the
        // run provenance is intact.
        $draft = EditorialContent::query()->findOrFail($request->resulting_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $draft->status);
        $this->assertTrue((bool) $draft->ai_generated);
        $this->assertNotNull($draft->generation_run_id);
        $this->assertNotSame($this->publishedEn->id, $draft->id);

        // The request snapshot before preparation is unchanged apart from the
        // processing fields (customer request text preserved verbatim).
        $this->assertSame($before['request_text'], $request->request_text);
        $this->assertSame(EditorialRevisionRequest::BILLING_COMPLIMENTARY, $request->billing_classification);
    }

    public function test_prepared_en_version_does_not_mutate_the_published_version(): void
    {
        $this->prepareAiDraft();

        $prepared = $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $prepared->refresh();

        $this->assertSame(EditorialContent::STATUS_APPROVED, $prepared->status);
        $this->assertGreaterThan($this->publishedEn->version_number, $prepared->version_number);
        $this->assertStringContainsString('Chairman', $prepared->body);

        $this->publishedEn->refresh();
        $this->assertSame("He served as Director of ABC Foundation from 2019 to 2024.\n\nHe was known for civic dialogue.", $this->publishedEn->body);
        $this->assertSame($this->application->published_english_editorial_content_id, $this->publishedEn->id);
    }

    public function test_prepared_ml_version_does_not_mutate_the_published_ml_version(): void
    {
        $this->prepareAiDraft();

        $mlDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_malayalam_editorial_content_id);
        $mlPrepared = app(EditorialContentVersioningService::class)->applyEditorUpdate($mlDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'title' => $mlDraft->title,
            'summary' => $mlDraft->summary,
            'body' => 'അദ്ദേഹം 2019 മുതൽ 2026 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.',
        ], $this->admin);

        $this->publishedMl->refresh();
        $this->assertSame('അദ്ദേഹം 2019 മുതൽ 2024 വരെ ഡയറക്ടറായി സേവനമനുഷ്ഠിച്ചു.', $this->publishedMl->body);
        $this->assertGreaterThan($this->publishedMl->version_number, $mlPrepared->version_number);
        $this->assertStringContainsString('2026', $mlPrepared->body);
        $this->assertStringNotContainsString('2024', $mlPrepared->body);
    }

    public function test_release_links_prepared_versions_and_customer_receives_prepared_not_raw_ai(): void
    {
        $this->prepareAiDraft();

        // The editor revises the raw AI draft into the Jannayaks-prepared,
        // approved version before release. The customer only ever sees the
        // editor-approved version — never an unreviewed AI draft.
        $prepared = $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");

        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);

        $request = $this->openRequest();
        $this->assertSame(EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW, $request->status);
        $this->assertSame($prepared->id, (int) $request->resulting_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $prepared->status);
        $this->assertNotNull($request->maintenance_preview_released_at);

        $this->application->refresh();
        $this->assertSame($prepared->id, (int) $this->application->preview_english_editorial_content_id);

        // The customer preview page renders the prepared version and never
        // exposes internal AI metadata.
        $previewResponse = $this->actingAs($this->member)->get(route('applications.preview', $this->application));
        if ($previewResponse->isRedirection()) {
            dump('PREVIEW REDIRECT TARGET: '.$previewResponse->getTargetUrl());
        }
        $previewResponse
            ->assertOk()
            ->assertSee('Chairman of ABC Foundation')
            ->assertDontSee('AI provider')
            ->assertDontSee('generation_run');
    }

    public function test_customer_can_approve_the_prepared_version(): void
    {
        $this->prepareAiDraft();
        $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $preparedId = (int) $this->application->fresh()->preview_english_editorial_content_id;

        $this->maintenance->approveMaintenancePreview($this->application, $this->member, $preparedId);

        $request = $this->openRequest();
        $this->assertSame(EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED, $request->status);
        $this->assertSame($preparedId, (int) $request->approved_english_editorial_content_id);
        $this->assertSame($preparedId, (int) $this->application->fresh()->customer_approved_english_editorial_content_id);
        $this->assertSame(1, EditorialCustomerApproval::query()->where('application_id', $this->application->id)->count());
        $this->assertSame(Application::STATUS_PUBLISHED, $this->application->fresh()->status);
    }

    public function test_customer_minor_correction_stays_in_the_same_maintenance_cycle(): void
    {
        $this->prepareAiDraft();
        $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $classificationBefore = $this->openRequest()->billing_classification;
        $nextEligibleBefore = $this->openRequest()->next_eligible_on?->toDateString();

        $this->maintenance->requestCorrection($this->application, $this->member, 'Please change 2026 to 2025 in the final line.');

        $request = $this->openRequest();
        $this->assertSame(EditorialRevisionRequest::STATUS_IN_PROGRESS, $request->status);
        $this->assertSame('Please change 2026 to 2025 in the final line.', $request->customer_correction_text);

        // Same cycle: classification, entitlement snapshot and request count
        // unchanged — no new complimentary entitlement, no new paid request.
        $this->assertSame($classificationBefore, $request->billing_classification);
        $this->assertSame($nextEligibleBefore, $request->next_eligible_on?->toDateString());
        $this->assertSame(1, EditorialRevisionRequest::query()->where('application_id', $this->application->id)->count());
    }

    public function test_editor_applies_acceptable_correction_via_immutable_successor(): void
    {
        $this->prepareAiDraft();
        $prepared = $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->requestCorrection($this->application, $this->member, 'Please change 2026 to 2025 in the final line.');

        // Editorial control: the editor saves corrected EN/ML through the
        // maintenance preparation path — the correction round creates the
        // EN v4 / ML v4 successor pair with pairing established at creation.
        // (On a bilingual profile an EN-only correction is not releasable:
        // both languages are prepared together.)
        $mlPrior = $this->openRequest()->resulting_malayalam_editorial_content_id;
        $this->maintenance->prepareVersions(
            $this->application,
            $this->admin,
            [
                'title' => $prepared->title,
                'summary' => $prepared->summary,
                'body' => "He served as Chairman of ABC Foundation from 2019 to 2025.\n\nHe was known for civic dialogue.",
            ],
            [
                'body' => 'അദ്ദേഹം 2019 മുതൽ 2025 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.',
            ],
        );

        $corrected = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);
        $correctedMl = EditorialContent::query()->findOrFail($this->openRequest()->resulting_malayalam_editorial_content_id);
        $prepared->refresh();
        $this->assertSame("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.", $prepared->body);
        $this->assertGreaterThan($prepared->version_number, $corrected->version_number);
        $this->assertGreaterThan((int) $mlPrior, $correctedMl->id);
        $this->assertSame($corrected->id, (int) $correctedMl->source_editorial_content_id);

        // Release requires the corrected version to be approved first.
        try {
            $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
            $this->fail('Release must refuse unapproved prepared versions.');
        } catch (InvalidArgumentException) {
        }

        // Editor approves the corrected EN/ML drafts in place (existing draft workflow).
        app(EditorialContentVersioningService::class)->applyEditorUpdate($corrected, [
            'status' => EditorialContent::STATUS_APPROVED,
        ], $this->admin);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($correctedMl, [
            'status' => EditorialContent::STATUS_APPROVED,
        ], $this->admin);
        $corrected->refresh();
        $this->assertSame(EditorialContent::STATUS_APPROVED, $corrected->status);

        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->application->refresh();
        $this->assertSame($corrected->id, (int) $this->application->preview_english_editorial_content_id);
        $this->assertSame($correctedMl->id, (int) $this->application->preview_malayalam_editorial_content_id);
        $this->assertSame(EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW, $this->openRequest()->status);

        $this->maintenance->approveMaintenancePreview($this->application, $this->member, $corrected->id);
        $this->assertSame(EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED, $this->openRequest()->status);
        $this->assertSame($corrected->id, (int) $this->openRequest()->approved_english_editorial_content_id);
    }

    public function test_correction_round_en_ml_pairing_is_correct_before_customer_preview(): void
    {
        // Full correction round: EN v4 ↔ ML v4 successors are created with
        // correct pairing at creation — before approval and before release.
        $this->prepareAiDraft();

        $enDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);
        $mlDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_malayalam_editorial_content_id);

        // Round 1 preparation: EN approved; ML edited in place (still a draft
        // working copy — pairing re-established while unapproved).
        $enApproved = app(EditorialContentVersioningService::class)->applyEditorUpdate($enDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'He served as Chairman of ABC Foundation from 2019 to 2026.',
        ], $this->admin);
        $mlApproved = app(EditorialContentVersioningService::class)->applyEditorUpdate($mlDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'അദ്ദേഹം 2019 മുതൽ 2026 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.',
        ], $this->admin, $enApproved->id);

        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->requestCorrection($this->application, $this->member, 'Please change 2026 to 2025.');

        $this->maintenance->prepareVersions(
            $this->application,
            $this->admin,
            ['body' => 'He served as Chairman of ABC Foundation from 2019 to 2025.'],
            ['body' => 'അദ്ദേഹം 2019 മുതൽ 2025 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.'],
        );

        $request = $this->openRequest();
        $newEn = EditorialContent::query()->findOrFail($request->resulting_english_editorial_content_id);
        $newMl = EditorialContent::query()->findOrFail($request->resulting_malayalam_editorial_content_id);

        // Pairing is correct NOW — while both versions are still drafts,
        // before approval and before any customer visibility.
        $this->assertSame(EditorialContent::STATUS_DRAFT, $newEn->status);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $newMl->status);
        $this->assertSame($newEn->id, (int) $newMl->source_editorial_content_id);
        $this->assertGreaterThan($enApproved->version_number, $newEn->version_number);
        $this->assertGreaterThan($mlApproved->version_number, $newMl->version_number);

        // Previous proposed versions were not modified.
        $enApproved->refresh();
        $mlApproved->refresh();
        $this->assertSame('He served as Chairman of ABC Foundation from 2019 to 2026.', $enApproved->body);
        $this->assertSame('അദ്ദേഹം 2019 മുതൽ 2026 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.', $mlApproved->body);

        // Editor approves the prepared pair in place; pairing is unchanged.
        app(EditorialContentVersioningService::class)->applyEditorUpdate($newEn, ['status' => EditorialContent::STATUS_APPROVED], $this->admin);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($newMl, ['status' => EditorialContent::STATUS_APPROVED], $this->admin);
        $newEn->refresh();
        $newMl->refresh();
        $this->assertSame($newEn->id, (int) $newMl->source_editorial_content_id);

        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->application->refresh();
        $this->assertSame($newEn->id, (int) $this->application->preview_english_editorial_content_id);
        $this->assertSame($newMl->id, (int) $this->application->preview_malayalam_editorial_content_id);
    }

    public function test_release_refuses_unpaired_malayalam_on_a_bilingual_profile(): void
    {
        $this->prepareAiDraft();

        // The editor approves the EN master and the ML draft, but the ML
        // version's pairing points at the WRONG English master (fixture
        // simulates lineage drift). Release must refuse instead of
        // publishing an unpaired Malayalam edition.
        $enDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($enDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'He served as Chairman of ABC Foundation from 2019 to 2026.',
        ], $this->admin);

        $mlDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_malayalam_editorial_content_id);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($mlDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
        ], $this->admin);

        EditorialContent::query()->whereKey($mlDraft->id)->update([
            'source_editorial_content_id' => $this->publishedEn->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not paired with the prepared English master');
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
    }

    public function test_publication_performs_no_editorial_content_mutation(): void
    {
        $this->prepareAiDraft();
        $enDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);
        $mlDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_malayalam_editorial_content_id);
        $en = app(EditorialContentVersioningService::class)->applyEditorUpdate($enDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'He served as Chairman of ABC Foundation from 2019 to 2026.',
        ], $this->admin);
        $ml = app(EditorialContentVersioningService::class)->applyEditorUpdate($mlDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'അദ്ദേഹം 2019 മുതൽ 2026 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.',
        ], $this->admin, $en->id);
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->approveMaintenancePreview($this->application, $this->member, $en->id);

        $snapshotBefore = EditorialContent::query()
            ->where('profile_id', $this->application->profile_id)
            ->get()
            ->map(fn (EditorialContent $c): string => implode('|', [
                $c->id, $c->language, (string) $c->version_number, $c->status,
                (string) $c->source_editorial_content_id, $c->body, $c->title, $c->summary,
            ]))
            ->sort()
            ->values()
            ->toJson();

        $this->maintenance->completePublication($this->application, $this->admin);

        $snapshotAfter = EditorialContent::query()
            ->where('profile_id', $this->application->profile_id)
            ->get()
            ->map(fn (EditorialContent $c): string => implode('|', [
                $c->id, $c->language, (string) $c->version_number, $c->status,
                (string) $c->source_editorial_content_id, $c->body, $c->title, $c->summary,
            ]))
            ->sort()
            ->values()
            ->toJson();

        // Publication mutates nothing on any EditorialContent row — pairing
        // was established at creation, so there is nothing to repair.
        $this->assertSame($snapshotBefore, $snapshotAfter);

        $this->application->refresh();
        $this->assertSame($en->id, (int) $this->application->published_english_editorial_content_id);
        $this->assertSame($ml->id, (int) $this->application->published_malayalam_editorial_content_id);
        $this->assertSame($en->id, (int) EditorialContent::query()->find($ml->id)->source_editorial_content_id);
    }

    public function test_publication_uses_the_approved_versions_and_preserves_history(): void
    {
        $this->prepareAiDraft();
        $prepared = $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->approveMaintenancePreview($this->application, $this->member, $prepared->id);
        $originalEnBody = $this->publishedEn->body;
        $originalEnId = $this->publishedEn->id;

        $this->maintenance->completePublication($this->application, $this->admin);

        $this->application->refresh();
        $this->assertSame(Application::STATUS_PUBLISHED, $this->application->status);
        $this->assertSame($prepared->id, (int) $this->application->published_english_editorial_content_id);

        $request = $this->openRequest();
        $this->assertSame(EditorialRevisionRequest::STATUS_COMPLETED, $request->status);
        $this->assertSame($prepared->id, (int) $request->approved_english_editorial_content_id);

        // Previous published version remains immutable history; the profile
        // anchor (published_at) and slug/reference are untouched.
        $this->publishedEn->refresh();
        $this->assertSame($originalEnBody, $this->publishedEn->body);
        $this->assertSame($originalEnId, $this->publishedEn->id);
        $this->assertSame('2026-06-01', $this->application->profile->published_at->toDateString());
        $this->assertSame('arun.kumar.nair', $this->application->profile->slug);
        $this->assertNotNull($this->application->profile->reference_code);
    }

    public function test_public_profile_serves_paired_en_and_ml_after_a_correction_round(): void
    {
        // Full bilingual cycle with a correction round: AI drafts → editor
        // approves the paired drafts → release → customer correction →
        // prepareVersions creates the corrected EN v4 ↔ ML v4 pair (paired
        // at creation) → approve → release → approve → publish → the public
        // profile serves the corrected pair in both languages.
        $this->prepareAiDraft();

        $enDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);
        $mlDraft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_malayalam_editorial_content_id);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($enDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'He served as Chairman of ABC Foundation from 2019 to 2026.',
        ], $this->admin);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($mlDraft, [
            'status' => EditorialContent::STATUS_APPROVED,
            'body' => 'അദ്ദേഹം 2019 മുതൽ 2026 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.',
        ], $this->admin, $enDraft->id);

        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->requestCorrection($this->application, $this->member, 'Please change 2026 to 2025.');

        $this->maintenance->prepareVersions(
            $this->application,
            $this->admin,
            ['body' => 'He served as Chairman of ABC Foundation from 2019 to 2025.'],
            ['body' => 'അദ്ദേഹം 2019 മുതൽ 2025 വരെ ചെയർമാനായി സേവനമനുഷ്ഠിച്ചു.'],
        );

        $request = $this->openRequest();
        $enFinal = EditorialContent::query()->findOrFail($request->resulting_english_editorial_content_id);
        $mlFinal = EditorialContent::query()->findOrFail($request->resulting_malayalam_editorial_content_id);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($enFinal, ['status' => EditorialContent::STATUS_APPROVED], $this->admin);
        app(EditorialContentVersioningService::class)->applyEditorUpdate($mlFinal, ['status' => EditorialContent::STATUS_APPROVED], $this->admin);

        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->approveMaintenancePreview(
            $this->application,
            $this->member,
            (int) $this->application->fresh()->preview_english_editorial_content_id,
        );
        $this->maintenance->completePublication($this->application, $this->admin);

        $this->application->refresh();
        $this->assertSame($enFinal->id, (int) $this->application->published_english_editorial_content_id);
        $this->assertSame($mlFinal->id, (int) $this->application->published_malayalam_editorial_content_id);

        $this->get('/arun.kumar.nair')
            ->assertOk()
            ->assertSee('Chairman of ABC Foundation from 2019 to 2025')
            ->assertDontSee('from 2019 to 2026');

        $this->get('/arun.kumar.nair?lang=ml')
            ->assertOk()
            ->assertSee('2019 മുതൽ 2025 വരെ')
            ->assertDontSee('2019 മുതൽ 2026 വരെ');
    }

    public function test_public_profile_serves_the_newly_published_version(): void
    {
        $this->prepareAiDraft();
        $prepared = $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->approveMaintenancePreview($this->application, $this->member, $prepared->id);
        $this->maintenance->completePublication($this->application, $this->admin);

        $this->get('/arun.kumar.nair')
            ->assertOk()
            ->assertSee('Chairman of ABC Foundation')
            ->assertDontSee('Director of ABC Foundation');
    }

    public function test_customer_cannot_drive_editorial_preparation_or_publication(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->maintenance->prepareViaAiDraft($this->application, $this->member);
    }

    public function test_customer_cannot_release_or_publish_maintenance_updates(): void
    {
        $this->prepareAiDraft();

        $this->expectException(InvalidArgumentException::class);
        $this->maintenance->releaseMaintenancePreview($this->application, $this->member);
    }

    public function test_customer_cannot_complete_publication(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->maintenance->completePublication($this->application, $this->member);
    }

    public function test_maintenance_flow_leaves_media_lane_untouched(): void
    {
        $mediaBefore = MediaItem::query()->count();

        $this->prepareAiDraft();
        $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->approveMaintenancePreview($this->application, $this->member, (int) $this->application->fresh()->preview_english_editorial_content_id);
        $this->maintenance->completePublication($this->application, $this->admin);

        $this->assertSame($mediaBefore, MediaItem::query()->count());
    }

    public function test_pre_publication_release_cannot_interfere_with_an_open_maintenance_cycle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(\App\Services\CustomerEditorialWorkflowService::class)->releaseForCustomerPreview(
            $this->application->fresh(),
            $this->admin,
        );
    }

    public function test_maintenance_diff_compares_published_versus_proposed(): void
    {
        $this->prepareAiDraft();
        $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2025.\n\nHe was known for civic dialogue and public service.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);

        $html = \App\Filament\Resources\Applications\Schemas\ApplicationInfolist::maintenanceDiffHtml($this->application);

        $this->assertStringContainsString('Old — current published', $html);
        $this->assertStringContainsString('<del', $html);
        $this->assertStringContainsString('<ins', $html);
        $this->assertStringContainsString('2024', strip_tags($html));
        $this->assertStringContainsString('2025', strip_tags($html));
        // The comparison never includes the customer's request text.
        $this->assertStringNotContainsString('Director to Chairman from 2025, and update', $html);
    }

    public function test_old_and_new_texts_differ_and_highlight_the_change(): void
    {
        $diff = \App\Support\TextDiffHighlighter::diff(
            'He served as Director of ABC Foundation from 2019 to 2024.',
            'He served as Director of ABC Foundation from 2019 to 2026.',
        );

        $this->assertStringContainsString('<del', $diff['old_html']);
        $this->assertStringContainsString('<ins', $diff['new_html']);
        $this->assertStringContainsString('2024', $diff['old_html']);
        $this->assertStringContainsString('2026', $diff['new_html']);
        $this->assertStringContainsString('Director', $diff['old_html']);
    }

    public function test_maintenance_payload_includes_published_content_and_request(): void
    {
        $payload = app(\App\Support\EditorialSourcePayloadBuilder::class)->build($this->application->fresh());

        $this->assertArrayHasKey('maintenance_context', $payload);
        $this->assertStringContainsString('Director to Chairman from 2025', $payload['maintenance_context']['customer_update_request']);
        $this->assertStringContainsString('ABC Foundation', $payload['maintenance_context']['current_published_english']['body']);
        $this->assertSame('SOURCE_DATA_ONLY', $payload['data_boundary']);
    }

    public function test_prepared_version_must_be_approved_before_release(): void
    {
        $this->prepareAiDraft();

        // Simulate a draft that was never approved: the release must refuse.
        // (The AI draft is a draft; without editor approval there is nothing
        // newer than the published version to release.)
        $request = $this->openRequest();
        $request->forceFill(['resulting_english_editorial_content_id' => null, 'resulting_malayalam_editorial_content_id' => null])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
    }

    public function test_correction_requires_an_awaiting_preview(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->maintenance->requestCorrection($this->application, $this->member, 'Please change something that was never released.');
    }

    public function test_request_text_is_never_attached_to_editorial_content(): void
    {
        $this->prepareAiDraft();

        $draft = EditorialContent::query()->findOrFail($this->openRequest()->resulting_english_editorial_content_id);
        $this->assertStringNotContainsString('Please change Director to Chairman', (string) $draft->body);

        $this->assertStringContainsString(
            'Please change Director to Chairman',
            (string) $this->openRequest()->request_text,
        );
    }

    public function test_published_reference_and_identity_unchanged_after_publication(): void
    {
        $this->prepareAiDraft();
        $prepared = $this->editorApprovesDraft("He served as Chairman of ABC Foundation from 2019 to 2026.\n\nHe was known for civic dialogue.");
        $this->maintenance->releaseMaintenancePreview($this->application, $this->admin);
        $this->maintenance->approveMaintenancePreview($this->application, $this->member, $prepared->id);
        $referenceBefore = $this->application->profile->reference_code;

        $this->maintenance->completePublication($this->application, $this->admin);

        $this->application->refresh();
        $this->assertSame($referenceBefore, $this->application->profile->reference_code);
        $this->assertSame('Arun Kumar Nair', $this->application->profile->full_name);
        $this->assertTrue(Str::is('arun.kumar.nair', (string) $this->application->profile->slug));
    }
}

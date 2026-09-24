<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\ConsentRecordingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_interview_submission_records_ai_processing_consent(): void
    {
        [$user, $application] = $this->seedPaidApplicationWithRequiredAnswers();
        config(['online_interview.submission.prevent_duplicate_within_seconds' => 0]);

        $this->actingAs($user)->postJson(route('online-interview.submit', ['application' => $application->id]))
            ->assertOk();

        $this->assertSame(1, ConsentRecord::query()->count());
        $consent = ConsentRecord::query()->firstOrFail();
        $this->assertSame($user->id, (int) $consent->user_id);
        $this->assertSame(ConsentRecordingService::KEY_INTERVIEW_AI_PROCESSING, (string) $consent->consent_key);
        $this->assertTrue((bool) $consent->consented);
        $this->assertNotNull($consent->action_at);
    }

    public function test_repeated_recording_is_idempotent_per_user_and_key(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $service = app(ConsentRecordingService::class);

        $first = $service->recordOnce($user, ConsentRecordingService::KEY_INTERVIEW_AI_PROCESSING, '203.0.113.9', 'UA');
        $second = $service->recordOnce($user, ConsentRecordingService::KEY_INTERVIEW_AI_PROCESSING, '198.51.100.1', 'Other');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ConsentRecord::query()->where('user_id', $user->id)->count());
    }

    public function test_distinct_consent_keys_get_distinct_rows(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $service = app(ConsentRecordingService::class);

        $service->recordOnce($user, ConsentRecordingService::KEY_INTERVIEW_AI_PROCESSING);
        $service->recordOnce($user, ConsentRecordingService::KEY_EDITORIAL_APPROVAL_PUBLICATION);

        $this->assertSame(2, ConsentRecord::query()->where('user_id', $user->id)->count());
    }

    public function test_customer_approval_records_publication_consent(): void
    {
        [$member, $application, $englishId] = $this->seedApprovablePreview();

        $this->actingAs($member)->postJson(route('applications.preview.approve', ['application' => $application->id]), [
            'english_editorial_content_id' => $englishId,
            'confirm_approval' => true,
        ])->assertRedirect();

        $approval = ConsentRecord::query()
            ->where('consent_key', ConsentRecordingService::KEY_EDITORIAL_APPROVAL_PUBLICATION)
            ->firstOrFail();
        $this->assertSame($member->id, (int) $approval->user_id);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function seedPaidApplicationWithRequiredAnswers(): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'mobile_verified_at' => now()]);
        $application = Application::factory()->paid()->create([
            'user_id' => $user->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Consent Leader',
        ]);

        $ids = \App\Support\OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $answers = [];
        foreach ($ids as $id) {
            $answers[$id] = 'A consented answer.';
        }
        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $application->id]), ['answers' => $answers])
            ->assertOk();

        return [$user, $application->fresh()];
    }

    /**
     * Seed the full editorial state machine up to an approvable released preview.
     *
     * @return array{0: User, 1: Application, 2: int}
     */
    private function seedApprovablePreview(): array
    {
        config(['jannayaks.ai.provider' => 'fake']);
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Approval Consent Leader',
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        \App\Models\InterviewAnswer::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'question_id' => 'q1',
            'original_answer' => 'Served the community since 2005.',
            'answered_at' => now(),
        ]);

        app(\App\Services\EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);

        $english = \App\Models\EditorialContent::query()
            ->where('profile_id', $application->fresh()->profile_id)
            ->where('language', \App\Models\EditorialContent::LANGUAGE_EN)
            ->orderByDesc('version_number')
            ->firstOrFail();
        $english->forceFill([
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
        ])->save();

        app(\App\Services\CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application->fresh(), $editor);

        return [$member, $application->fresh(), (int) $english->id];
    }
}

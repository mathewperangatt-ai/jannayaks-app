<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\AiEditorialRun;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\EditorialGenerationService;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireStaffSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_questionnaire_submission_creates_staff_review_signal_without_generating_ai(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Signal Citizen',
        ])->paid()->create();

        $this->assertSame(0, EditorialContent::query()->count());
        $this->assertNull(ApplicationResource::getNavigationBadge());

        $requiredIds = OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $answers = [];
        foreach ($requiredIds as $id) {
            $answers[$id] = 'Answer for '.$id;
        }

        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $application->id]), [
            'answers' => $answers,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('online-interview.submit', ['application' => $application->id]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            ]);

        $application->refresh();
        $this->assertSame(Application::STATUS_AWAITING_EDITORIAL_REVIEW, $application->status);
        $this->assertSame('1', ApplicationResource::getNavigationBadge());
        $this->assertSame('warning', ApplicationResource::getNavigationBadgeColor());

        $this->assertTrue(
            StaffActionLog::query()
                ->where('action', 'application.questionnaire_submitted')
                ->where('subject_id', $application->id)
                ->where('actor_user_id', $user->id)
                ->exists()
        );

        $this->assertSame(0, EditorialContent::query()->count());
        $this->assertSame(0, AiEditorialRun::query()->count());
    }

    public function test_questionnaire_idor_is_blocked_and_customers_cannot_trigger_ai(): void
    {
        $alice = User::factory()->create(['email_verified_at' => now()]);
        $bob = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $alice->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ])->paid()->create();

        $this->actingAs($bob)->getJson(route('online-interview.show', $application))->assertForbidden();
        $this->actingAs($bob)->postJson(route('online-interview.submit', $application))->assertForbidden();

        $this->expectException(\InvalidArgumentException::class);
        app(EditorialGenerationService::class)->generateForApplication($application, $alice);
    }
}

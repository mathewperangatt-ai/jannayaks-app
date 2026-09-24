<?php

namespace Tests\Feature;

use App\Models\AiEditorialRun;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaleAiEditorialRunRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_running_run_is_marked_failed_and_recoverable(): void
    {
        $application = Application::factory()->create([
            'package_tier' => 'emerging',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        $run = AiEditorialRun::query()->create([
            'application_id' => $application->id,
            'provider' => 'fake',
            'status' => AiEditorialRun::STATUS_RUNNING,
            'stage' => AiEditorialRun::STAGE_ENGLISH,
            'started_at' => now()->subMinutes(60),
        ]);

        $exit = Artisan::call('jannayaks:fail-stale-ai-runs');

        $this->assertSame(0, $exit);
        $run->refresh();
        $this->assertSame(AiEditorialRun::STATUS_FAILED, $run->status);
        $this->assertSame('stale_run_recovered', (string) $run->error_code);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->fresh()->finished_at);
    }

    public function test_recent_running_run_is_left_untouched(): void
    {
        $application = Application::factory()->create([
            'package_tier' => 'emerging',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        $run = AiEditorialRun::query()->create([
            'application_id' => $application->id,
            'provider' => 'fake',
            'status' => AiEditorialRun::STATUS_RUNNING,
            'stage' => AiEditorialRun::STAGE_ENGLISH,
            'started_at' => now()->subMinutes(2),
        ]);

        Artisan::call('jannayaks:fail-stale-ai-runs');

        $run->refresh();
        $this->assertSame(AiEditorialRun::STATUS_RUNNING, $run->status);
        $this->assertNull($run->error_code);
    }

    public function test_terminal_runs_are_never_touched(): void
    {
        $application = Application::factory()->create([
            'package_tier' => 'emerging',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        $succeeded = AiEditorialRun::query()->create([
            'application_id' => $application->id,
            'provider' => 'fake',
            'status' => AiEditorialRun::STATUS_SUCCEEDED,
            'started_at' => now()->subHours(6),
            'finished_at' => now()->subHours(5),
        ]);
        $failed = AiEditorialRun::query()->create([
            'application_id' => $application->id,
            'provider' => 'fake',
            'status' => AiEditorialRun::STATUS_FAILED,
            'error_code' => 'provider_http_error',
            'started_at' => now()->subHours(6),
            'finished_at' => now()->subHours(5),
        ]);

        Artisan::call('jannayaks:fail-stale-ai-runs');

        $this->assertSame(AiEditorialRun::STATUS_SUCCEEDED, $succeeded->fresh()->status);
        $this->assertSame(AiEditorialRun::STATUS_FAILED, $failed->fresh()->status);
        $this->assertSame('provider_http_error', (string) $failed->fresh()->error_code);
    }

    public function test_minutes_option_overrides_default_threshold(): void
    {
        $application = Application::factory()->create([
            'package_tier' => 'emerging',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        $run = AiEditorialRun::query()->create([
            'application_id' => $application->id,
            'provider' => 'fake',
            'status' => AiEditorialRun::STATUS_RUNNING,
            'started_at' => now()->subMinutes(3),
        ]);

        Artisan::call('jannayaks:fail-stale-ai-runs', ['--minutes' => '2']);

        $run->refresh();
        $this->assertSame(AiEditorialRun::STATUS_FAILED, $run->status);
    }

    public function test_wedged_application_can_generate_again_after_recovery(): void
    {
        config(['jannayaks.ai.provider' => 'fake']);
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Wedged Leader',
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        // Simulate a crashed run holding the per-application partial unique index.
        AiEditorialRun::query()->create([
            'application_id' => $application->id,
            'provider' => 'fake',
            'status' => AiEditorialRun::STATUS_RUNNING,
            'started_at' => now()->subHour(),
        ]);

        Artisan::call('jannayaks:fail-stale-ai-runs');

        // Generation is no longer blocked by the unique running-row index.
        $this->expectNotToPerformAssertions();
        app(\App\Services\EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Cloudflare Turnstile (Managed mode) on the Online Interview submission:
 * enabled only when sitekey+secret are configured; mandatory server-side
 * Siteverify verification; missing/invalid/expired/reused tokens rejected;
 * applicant answers preserved on failed verification; the workflow stays
 * unchanged when the integration is disabled.
 */
class InterviewTurnstileTest extends TestCase
{
    use RefreshDatabase;

    private function interviewApplication(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = \App\Models\Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Turnstile Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ]);

        // Pre-save one interview answer server-side (simulating autosave):
        // this is the answer that must SURVIVE a failed verification.
        app(\App\Services\OnlineInterviewService::class)->saveAnswers(
            $application->fresh(),
            ['q1' => 'Turnstile preservation answer.'],
        );

        return [$member, $application->fresh()];
    }

    private function enableTurnstile(): void
    {
        Config::set('services.turnstile.sitekey', 'sitekey-test');
        Config::set('services.turnstile.secret', 'secret-test');
    }

    private function submitPayload(array $extra = []): array
    {
        return array_merge([
            'answers' => ['q1' => 'Turnstile preservation answer.', 'q2' => 'New answer after retry.'],
            'questionnaire_confirmed' => '1',
            'accuracy_confirmed' => '1',
        ], $extra);
    }

    private function siteverifyBody(bool $success): array
    {
        return ['success' => $success, 'error-codes' => $success ? [] : ['invalid-input-response']];
    }

    public function test_submission_works_without_turnstile_when_integration_disabled(): void
    {
        [$member, $application] = $this->interviewApplication();
        Config::set('services.turnstile.sitekey', null);
        Config::set('services.turnstile.secret', null);

        $this->actingAs($member)
            ->postJson(route('online-interview.submit', $application), $this->submitPayload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $application->refresh();
        $this->assertTrue($application->isInterviewSubmitted(), 'Submission proceeds with the integration disabled.');
    }

    public function test_missing_token_is_rejected_and_answers_are_preserved(): void
    {
        [$member, $application] = $this->interviewApplication();
        $this->enableTurnstile();

        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => $this->siteverifyBody(true)]);
        $this->actingAs($member)
            ->from(route('online-interview.show', $application))
            ->post(route('online-interview.submit', $application), $this->submitPayload(['cf-turnstile-response' => '']))
            ->assertRedirect(route('online-interview.show', $application))
            ->assertSessionHasErrors(['turnstile']);

        Http::assertSentCount(0, Http::fake()); // no siteverify call without a token — fail closed
        $application->refresh();
        $this->assertFalse($application->isInterviewSubmitted(), 'Rejected submission does not advance the workflow.');

        // The pre-existing answer survived the failed verification.
        $this->assertSame(
            'Turnstile preservation answer.',
            \App\Models\InterviewAnswer::where('application_id', $application->id)->where('question_id', 'q1')->value('original_answer'),
        );
    }

    public function test_invalid_token_is_rejected_and_answers_are_preserved(): void
    {
        [$member, $application] = $this->interviewApplication();
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response($this->siteverifyBody(false), 200),
        ]);

        $this->actingAs($member)
            ->from(route('online-interview.show', $application))
            ->post(route('online-interview.submit', $application), $this->submitPayload(['cf-turnstile-response' => 'forged-token']))
            ->assertRedirect(route('online-interview.show', $application))
            ->assertSessionHasErrors(['turnstile']);

        $application->refresh();
        $this->assertFalse($application->isInterviewSubmitted());

        $this->assertSame(
            'Turnstile preservation answer.',
            \App\Models\InterviewAnswer::where('application_id', $application->id)->where('question_id', 'q1')->value('original_answer'),
        );
    }

    public function test_siteverify_network_failure_fails_closed(): void
    {
        [$member, $application] = $this->interviewApplication();
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('network down');
            },
        ]);

        $this->actingAs($member)
            ->post(route('online-interview.submit', $application), $this->submitPayload(['cf-turnstile-response' => 'token']))
            ->assertRedirect()
            ->assertSessionHasErrors(['turnstile']);

        $this->assertFalse($application->fresh()->isInterviewSubmitted(), 'Network failure fails closed.');
    }

    public function test_valid_token_verifies_and_submission_proceeds(): void
    {
        [$member, $application] = $this->interviewApplication();
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response($this->siteverifyBody(true), 200),
        ]);

        $this->actingAs($member)
            ->postJson(route('online-interview.submit', $application), $this->submitPayload(['cf-turnstile-response' => 'valid-token']))
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains($request->url(), 'siteverify')
                && ($data['secret'] ?? null) === 'secret-test'
                && ($data['response'] ?? null) === 'valid-token';
        });

        $application->refresh();
        $this->assertTrue($application->isInterviewSubmitted(), 'Verified submission proceeds to editorial processing.');
        $this->assertSame('New answer after retry.', \App\Models\InterviewAnswer::where('application_id', $application->id)->where('question_id', 'q2')->value('original_answer'));
    }

    public function test_widget_renders_only_when_enabled_and_hides_secret(): void
    {
        [$member, $application] = $this->interviewApplication();
        $this->enableTurnstile();

        $enabled = $this->actingAs($member)->get(route('online-interview.show', $application));
        $enabled->assertOk()
            ->assertSee('data-sitekey="sitekey-test"', false)
            ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);
        $this->assertStringNotContainsString('secret-test', $enabled->getContent(), 'The secret key is never exposed to the frontend.');

        Config::set('services.turnstile.sitekey', null);
        $disabled = $this->actingAs($member)->get(route('online-interview.show', $application));
        $disabled->assertOk()
            ->assertDontSee('cf-turnstile', false)
            ->assertDontSee('challenges.cloudflare.com/turnstile', false);
    }
}

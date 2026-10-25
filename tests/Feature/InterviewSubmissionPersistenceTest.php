<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InterviewSubmissionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_persists_the_answers_sent_with_the_submission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Latest Answer',
        ])->paid()->create();

        DB::table('interview_answers')->insert([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'question_id' => 'q1',
            'original_answer' => 'Older saved answer.',
            'answered_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('online-interview.submit', $application), [
                'answers' => ['q1' => 'The latest typed answer.'],
                'questionnaire_confirmed' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('interview_answers', [
            'application_id' => $application->id,
            'question_id' => 'q1',
            'original_answer' => 'The latest typed answer.',
        ]);
        $this->assertSame(1, Application::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($application->fresh()->online_interview_completed_at);

        $this->actingAs($user)
            ->post(route('online-interview.submit', $application), [
                'answers' => ['q1' => 'A later retry that must not replace the submitted answer.'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('interview_answers', [
            'application_id' => $application->id,
            'question_id' => 'q1',
            'original_answer' => 'The latest typed answer.',
        ]);
        $this->assertSame(1, Application::query()->where('user_id', $user->id)->count());
    }

    public function test_submit_validation_keeps_the_application_unsubmitted(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Missing Required',
        ])->paid()->create();

        $required = OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $this->assertNotEmpty($required);

        $this->actingAs($user)
            ->from(route('online-interview.show', $application))
            ->post(route('online-interview.submit', $application), [
                'answers' => [$required[0] => ''],
            ])
            ->assertRedirect(route('online-interview.show', $application))
            ->assertSessionHasErrors('submit');

        $this->assertNull($application->fresh()->online_interview_completed_at);
    }

    public function test_interview_page_submits_the_current_answers_and_dashboard_does_not(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Form Owner',
        ])->paid()->create();

        $this->actingAs($user)
            ->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertSee('jannayaksSubmitInterview', false);

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Continue Online Interview', false)
            ->assertDontSee('action="'.route('online-interview.submit', $application).'"', false);
    }

    public function test_direct_submission_stays_in_progress_until_editorial_handoff(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'distinguished',
            'source_method' => 'direct_submission',
            'full_name' => 'Direct Applicant',
            'status' => Application::STATUS_PAYMENT_PENDING,
        ])->create();
        $application->forceFill([
            'direct_submission_received_at' => now(),
            'status' => Application::STATUS_PAYMENT_PENDING,
        ])->save();

        $this->assertTrue($application->fresh()->isDirectSubmitted());

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('IN PROGRESS', false)
            ->assertDontSee('SUBMITTED', false);
    }

    public function test_a_published_profile_does_not_say_the_interview_is_still_being_prepared(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'full_name' => 'Published Applicant',
        ])->paid()->create();
        $application->forceFill([
            'status' => Application::STATUS_PUBLISHED,
            'online_interview_completed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Profile published', false)
            ->assertSee('Your profile is published.', false)
            ->assertDontSee('The editorial team is preparing your profile', false);
    }
}

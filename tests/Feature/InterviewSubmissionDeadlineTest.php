<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Support\InterviewSubmissionDeadline;
use App\Support\OnlineInterviewCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ten-calendar-day Online Interview submission period: Day 1 is the first
 * questionnaire start (business timezone), Day 10 is the last day to submit,
 * and an unsubmitted application is due for erasure on Day 11.
 */
class InterviewSubmissionDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private const INTRO_EN_HEADING = 'Take Your Time. Tell Your Story Thoughtfully.';

    private const INTRO_ML_HEADING = 'സമയമെടുത്ത്, ചിന്തിച്ച്, നിങ്ങളുടെ ജീവിതകഥ പങ്കുവെക്കൂ.';

    private const INTRO_EN_DELETION = 'will be permanently deleted on the 11th day. Your account will remain active, and you can start a fresh application whenever you wish to proceed.';

    private const INTRO_ML_DELETION = 'പതിനൊന്നാം ദിവസം സ്ഥിരമായി ഇല്ലാതാക്കുന്നതാണ്. നിങ്ങളുടെ അക്കൗണ്ട് സജീവമായി തുടരും.';

    private const PREPARATION_EN = 'for the Jannayaks team to prepare and review your profile before sharing it with you for your approval.';

    private const PREPARATION_ML = 'Jannayaks ടീമിന് <b>72 മണിക്കൂർ വരെ</b> സമയം അനുവദിക്കുക.';

    private const REMINDER_EN = 'from the day you first started this questionnaire — submit by the end of';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_day_one_is_the_first_questionnaire_start_not_application_creation(): void
    {
        $this->freeze('2026-11-01 09:00:00');
        [$member, $application] = $this->application();
        $this->assertNull($application->online_interview_started_at, 'Creating the application does not start the clock.');

        // 20:00 UTC on 9 Nov is 01:30 on 10 Nov in India: Day 1 is the business-timezone date.
        $this->freeze('2026-11-09 20:00:00');
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();

        $application->refresh();
        $this->assertSame('2026-11-09 20:00:00', $application->online_interview_started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10', InterviewSubmissionDeadline::startDay($application)->toDateString());
        $this->assertSame('2026-11-19', InterviewSubmissionDeadline::lastSubmissionDay($application)->toDateString());
        $this->assertSame('2026-11-20', InterviewSubmissionDeadline::erasureDay($application)->toDateString());
    }

    public function test_reopening_and_saving_never_reset_the_original_start(): void
    {
        $this->freeze('2026-11-10 04:00:00');
        [$member, $application] = $this->application();
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();
        $started = $application->fresh()->online_interview_started_at->toIso8601String();

        $this->freeze('2026-11-15 10:00:00');
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();
        $this->actingAs($member)->patchJson(route('online-interview.save', $application), ['answers' => ['q1' => 'Later answer']])->assertOk();

        $this->assertSame($started, $application->fresh()->online_interview_started_at->toIso8601String());
    }

    public function test_application_stays_valid_through_day_ten_and_is_due_on_day_eleven(): void
    {
        $this->freeze('2026-11-10 04:00:00'); // 09:30 IST, Day 1 = 10 Nov
        [$member, $application] = $this->application();
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();
        $application->refresh();

        $ist = fn (string $local) => CarbonImmutable::parse($local, 'Asia/Kolkata');
        $this->assertFalse(InterviewSubmissionDeadline::isDueForErasure($application, $ist('2026-11-10 09:31')), 'Day 1');
        $this->assertFalse(InterviewSubmissionDeadline::isDueForErasure($application, $ist('2026-11-19 00:00')), 'Day 10 start');
        $this->assertFalse(InterviewSubmissionDeadline::isDueForErasure($application, $ist('2026-11-19 23:59:59')), 'End of Day 10');
        $this->assertTrue(InterviewSubmissionDeadline::isDueForErasure($application, $ist('2026-11-20 00:00')), 'Day 11');
    }

    public function test_submission_on_day_ten_is_never_due_for_erasure(): void
    {
        $this->freeze('2026-11-10 04:00:00');
        [$member, $application] = $this->application();
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();
        $this->answerAllRequired($member, $application);

        $this->freeze('2026-11-19 18:29:00'); // 23:59 IST on Day 10
        $this->actingAs($member)->post(route('online-interview.submit', $application), [
            'questionnaire_confirmed' => '1',
            'accuracy_confirmed' => '1',
        ])->assertRedirect(route('applications.show', $application));

        $application->refresh();
        $this->assertTrue($application->isInterviewSubmitted());
        $this->assertFalse(InterviewSubmissionDeadline::isDueForErasure($application, CarbonImmutable::parse('2026-12-31 12:00', 'Asia/Kolkata')));
    }

    public function test_unstarted_application_has_no_deadline_and_is_not_due(): void
    {
        [, $application] = $this->application();

        $this->assertNull(InterviewSubmissionDeadline::lastSubmissionDay($application));
        $this->assertFalse(InterviewSubmissionDeadline::isDueForErasure($application, CarbonImmutable::parse('2030-01-01')));
    }

    public function test_full_bilingual_notice_appears_on_the_introduction_before_starting(): void
    {
        $this->get(route('apply'))
            ->assertOk()
            ->assertSee('Tell us about your achievements and journey.', false)
            ->assertSee(self::INTRO_EN_HEADING, false)
            ->assertSee('You have <b>10 calendar days</b> from the day you first start the questionnaire to complete and submit it.', false)
            ->assertSee(self::INTRO_EN_DELETION, false)
            ->assertSee(self::INTRO_ML_HEADING, false)
            ->assertSee('<b>10 കലണ്ടർ ദിവസങ്ങൾ</b> ലഭിക്കും.', false)
            ->assertSee(self::INTRO_ML_DELETION, false)
            ->assertSee('What Happens After Submission?', false)
            ->assertSee(self::PREPARATION_EN, false)
            ->assertSee('സമർപ്പിച്ചതിന് ശേഷം എന്ത് സംഭവിക്കും?', false)
            ->assertSee(self::PREPARATION_ML, false);
    }

    public function test_application_form_shows_the_full_notice_in_the_selected_language_only(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)->get(route('apply', ['lang' => 'en']))
            ->assertOk()
            ->assertSee(self::INTRO_EN_HEADING, false)
            ->assertSee(self::INTRO_EN_DELETION, false)
            ->assertSee(self::PREPARATION_EN, false)
            ->assertDontSee(self::INTRO_ML_HEADING, false)
            ->assertDontSee(self::PREPARATION_ML, false);

        $this->actingAs($member)->get(route('apply', ['lang' => 'ml']))
            ->assertOk()
            ->assertSee(self::INTRO_ML_HEADING, false)
            ->assertSee(self::INTRO_ML_DELETION, false)
            ->assertSee(self::PREPARATION_ML, false)
            ->assertDontSee(self::INTRO_EN_HEADING, false)
            ->assertDontSee(self::PREPARATION_EN, false);
    }

    public function test_after_starting_the_reminder_shows_the_actual_day_ten_date_not_day_eleven(): void
    {
        $this->freeze('2026-11-09 04:30:00'); // Day 1 = 9 Nov (IST)
        [$member, $application] = $this->application();

        $this->actingAs($member)->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertSee(self::REMINDER_EN, false)
            ->assertSee('<b>Wednesday, 18 November 2026</b>', false)
            ->assertSee('സമർപ്പിക്കേണ്ട അവസാന ദിവസം <b>18 November 2026</b>', false)
            ->assertDontSee('19 November 2026', false)
            ->assertSee('What Happens After Submission?', false)
            ->assertSee(self::PREPARATION_EN, false)
            ->assertSee(self::PREPARATION_ML, false)
            ->assertDontSee(self::INTRO_EN_DELETION, false);

        $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee(self::REMINDER_EN, false)
            ->assertSee('<b>Wednesday, 18 November 2026</b>', false)
            ->assertDontSee('19 November 2026', false);
    }

    public function test_notices_are_not_shown_after_submission(): void
    {
        [$member, $application] = $this->application();
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();
        $this->answerAllRequired($member, $application);
        $this->actingAs($member)->post(route('online-interview.submit', $application), [
            'questionnaire_confirmed' => '1',
            'accuracy_confirmed' => '1',
        ]);

        $this->actingAs($member)->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertDontSee(self::REMINDER_EN, false)
            ->assertDontSee(self::PREPARATION_EN, false);
        $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertDontSee(self::REMINDER_EN, false);
    }

    private function freeze(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($utc, 'UTC'));
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function application(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->paid()->create([
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
        ]);

        return [$member, $application->fresh()];
    }

    private function answerAllRequired(User $member, Application $application): void
    {
        $answers = [];
        foreach (OnlineInterviewCatalog::idsForTier('distinguished') as $qid) {
            $answers[$qid] = 'Answer for '.$qid;
        }
        $this->actingAs($member)->patchJson(route('online-interview.save', $application), ['answers' => $answers])->assertOk();
    }
}

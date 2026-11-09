<?php

namespace App\Support;

use App\Models\Application;
use Carbon\CarbonImmutable;

/**
 * Ten-calendar-day Online Interview submission period.
 *
 * Day 1 is the business-timezone calendar date on which the customer first
 * opened the questionnaire. Day 10 is the last day to submit; an unsubmitted
 * application becomes due for erasure on Day 11. Submission ends the period.
 */
class InterviewSubmissionDeadline
{
    public const SUBMISSION_DAYS = 10;

    /** Starts the clock once. Reopening or saving never moves it. */
    public static function recordFirstStart(Application $application): void
    {
        if ($application->online_interview_started_at !== null || $application->isInterviewSubmitted()) {
            return;
        }

        $now = now();
        $updated = Application::query()
            ->whereKey($application->id)
            ->whereNull('online_interview_started_at')
            ->whereNull('online_interview_completed_at')
            ->update(['online_interview_started_at' => $now]);

        $application->online_interview_started_at = $updated > 0
            ? $now
            : Application::query()->whereKey($application->id)->value('online_interview_started_at');
    }

    public static function timezone(): string
    {
        return (string) config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata');
    }

    public static function startDay(Application $application): ?CarbonImmutable
    {
        if ($application->online_interview_started_at === null) {
            return null;
        }

        return CarbonImmutable::parse($application->online_interview_started_at)
            ->setTimezone(self::timezone())
            ->startOfDay();
    }

    /** The tenth calendar day, inclusive: the last day on which submission is accepted. */
    public static function lastSubmissionDay(Application $application): ?CarbonImmutable
    {
        return self::startDay($application)?->addDays(self::SUBMISSION_DAYS - 1);
    }

    /** Day 11: the first day on which an unsubmitted application is due for erasure. */
    public static function erasureDay(Application $application): ?CarbonImmutable
    {
        return self::startDay($application)?->addDays(self::SUBMISSION_DAYS);
    }

    public static function isDueForErasure(Application $application, ?CarbonImmutable $at = null): bool
    {
        if ($application->isInterviewSubmitted()) {
            return false;
        }

        $erasureDay = self::erasureDay($application);
        if ($erasureDay === null) {
            return false;
        }

        $today = ($at ?? CarbonImmutable::now())->setTimezone(self::timezone())->startOfDay();

        return $today->greaterThanOrEqualTo($erasureDay);
    }
}

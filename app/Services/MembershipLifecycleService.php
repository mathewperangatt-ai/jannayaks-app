<?php

namespace App\Services;

use App\Mail\MembershipRenewalReminderMail;
use App\Models\Membership;
use App\Models\MembershipLifecycleEvent;
use App\Models\Payment;
use App\Models\Profile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Membership expiry / grace / deactivation / retention / renewal (Phase 15).
 *
 * Date authority: membership_lifecycle.business_timezone (default Asia/Kolkata).
 * Does not change the application-wide config('app.timezone').
 * - Membership is paid through ends_on (inclusive calendar day).
 * - Grace runs for grace_period_days calendar days after ends_on.
 *   Public while today <= ends_on + grace_period_days.
 *   Deactivation eligible when today > ends_on + grace_period_days.
 * - retention_until = ends_on + retention_years (not deactivation time).
 * - Reminder offsets are provisional configuration only.
 */
class MembershipLifecycleService
{
    public function __construct(
        private readonly StaffAuditLogger $auditLogger,
    ) {}

    public function businessTimezone(): string
    {
        $tz = (string) config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata');

        return $tz !== '' ? $tz : 'Asia/Kolkata';
    }

    public function today(): Carbon
    {
        return now($this->businessTimezone())->startOfDay();
    }

    public function gracePeriodDays(): int
    {
        return max(0, (int) config('jannayaks.membership_lifecycle.grace_period_days', 3));
    }

    public function retentionYears(): int
    {
        return max(1, (int) config('jannayaks.membership_lifecycle.retention_years', 1));
    }

    public function cycleYears(): int
    {
        return max(1, (int) config('jannayaks.tier_pricing.membership.cycle_years', 1));
    }

    /**
     * @return list<int>
     */
    public function reminderDaysBeforeExpiry(): array
    {
        return $this->normalizeDayOffsets(config('jannayaks.membership_lifecycle.reminder_days_before_expiry', []));
    }

    /**
     * @return list<int>
     */
    public function reminderDaysAfterExpiry(): array
    {
        return $this->normalizeDayOffsets(config('jannayaks.membership_lifecycle.reminder_days_after_expiry', []));
    }

    public function graceEndsOn(Membership $membership): ?Carbon
    {
        if ($membership->ends_on === null) {
            return null;
        }

        return $membership->ends_on->copy()->startOfDay()->addDays($this->gracePeriodDays());
    }

    public function computeRetentionUntil(CarbonInterface $endsOn): Carbon
    {
        return $endsOn->copy()->startOfDay()->addYears($this->retentionYears());
    }

    public function isWithinPaidPeriod(Membership $membership, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        if ($membership->ends_on === null) {
            return false;
        }

        return $on->lte($membership->ends_on->copy()->startOfDay());
    }

    public function isWithinGracePeriod(Membership $membership, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        if ($membership->ends_on === null) {
            return false;
        }

        $ends = $membership->ends_on->copy()->startOfDay();
        if ($on->lte($ends)) {
            return false;
        }

        $graceEnds = $this->graceEndsOn($membership);
        if ($graceEnds === null) {
            return false;
        }

        return $on->lte($graceEnds);
    }

    public function isPastGracePeriod(Membership $membership, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $graceEnds = $this->graceEndsOn($membership);
        if ($graceEnds === null) {
            return false;
        }

        return $on->gt($graceEnds);
    }

    public function isWithinRetention(Membership $membership, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $until = $membership->retention_until ?? (
            $membership->ends_on !== null
                ? $this->computeRetentionUntil($membership->ends_on)
                : null
        );

        if ($until === null) {
            return false;
        }

        return $on->lte($until->copy()->startOfDay());
    }

    public function lifecyclePhase(Membership $membership, ?CarbonInterface $on = null): string
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        if ($this->isWithinPaidPeriod($membership, $on)) {
            return 'active';
        }
        if ($this->isWithinGracePeriod($membership, $on)) {
            return 'grace';
        }
        if ($this->isWithinRetention($membership, $on)) {
            return 'lapsed_retained';
        }

        return 'retention_ended';
    }

    /**
     * Create the initial annual membership when a living profile is first published.
     * Idempotent: returns the existing membership if one already exists for the profile.
     */
    public function startMembershipForPublishedProfile(Profile $profile, ?CarbonInterface $startsOn = null): Membership
    {
        $existing = Membership::query()->where('profile_id', $profile->id)->first();
        if ($existing instanceof Membership) {
            return $existing;
        }

        $starts = ($startsOn ?? $this->today())->copy()->startOfDay();
        $ends = $starts->copy()->addYears($this->cycleYears());
        $retention = $this->computeRetentionUntil($ends);

        $membership = Membership::query()->create([
            'profile_id' => $profile->id,
            'status' => 'active',
            'tier' => 'basic',
            'starts_on' => $starts->toDateString(),
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $retention->toDateString(),
            'auto_renew' => false,
        ]);

        $this->recordEvent(
            $membership,
            'membership_started:'.$membership->id,
            MembershipLifecycleEvent::TYPE_STARTED,
            [
                'starts_on' => $membership->starts_on?->toDateString(),
                'ends_on' => $membership->ends_on?->toDateString(),
                'retention_until' => $membership->retention_until?->toDateString(),
            ],
        );

        $this->auditLogger->log(
            action: 'membership.started',
            subject: $membership,
            before: null,
            after: [
                'profile_id' => $profile->id,
                'starts_on' => $membership->starts_on?->toDateString(),
                'ends_on' => $membership->ends_on?->toDateString(),
                'retention_until' => $membership->retention_until?->toDateString(),
                'system' => true,
            ],
            actor: null,
        );

        return $membership;
    }

    /**
     * Calculate the next ends_on after a successful renewal payment.
     *
     * Before expiry or during grace: extend from current ends_on.
     * After grace (deactivated / lapsed, within retention): new period from today.
     */
    public function calculateRenewalPeriod(Membership $membership, ?CarbonInterface $on = null): array
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $cycle = $this->cycleYears();

        if ($membership->ends_on !== null && ! $this->isPastGracePeriod($membership, $on)) {
            $newEnds = $membership->ends_on->copy()->startOfDay()->addYears($cycle);
            $newStarts = $membership->starts_on?->copy()->startOfDay() ?? $on->copy();

            return [
                'starts_on' => $newStarts,
                'ends_on' => $newEnds,
                'retention_until' => $this->computeRetentionUntil($newEnds),
                'mode' => 'extend_from_ends_on',
            ];
        }

        $newStarts = $on->copy();
        $newEnds = $newStarts->copy()->addYears($cycle);

        return [
            'starts_on' => $newStarts,
            'ends_on' => $newEnds,
            'retention_until' => $this->computeRetentionUntil($newEnds),
            'mode' => 'restart_from_today',
        ];
    }

    /**
     * Apply a settled membership renewal payment. Authoritative success path only.
     */
    public function applySettledRenewal(Payment $payment): Membership
    {
        if ($payment->item_type !== Payment::ITEM_MEMBERSHIP) {
            throw new InvalidArgumentException('Payment is not a membership renewal.');
        }
        if (! $payment->isPaidOrBetter()) {
            throw new InvalidArgumentException('Renewal requires a settled payment.');
        }
        if ($payment->membership_id === null) {
            throw new InvalidArgumentException('Membership renewal payment is missing membership_id.');
        }

        return DB::transaction(function () use ($payment) {
            /** @var Membership $membership */
            $membership = Membership::query()->whereKey($payment->membership_id)->lockForUpdate()->firstOrFail();

            $renewKey = 'renewed:payment:'.$payment->id;
            if ($this->eventExists($membership->id, $renewKey)) {
                return $membership->fresh() ?? $membership;
            }

            $before = [
                'status' => $membership->status,
                'starts_on' => $membership->starts_on?->toDateString(),
                'ends_on' => $membership->ends_on?->toDateString(),
                'retention_until' => $membership->retention_until?->toDateString(),
                'lapsed_at' => $membership->lapsed_at?->toIso8601String(),
            ];

            $period = $this->calculateRenewalPeriod($membership, $this->renewalReferenceDay($payment));
            $wasPastGrace = $this->isPastGracePeriod($membership) || $membership->status === 'lapsed';

            $membership->forceFill([
                'status' => 'active',
                'starts_on' => $period['starts_on']->toDateString(),
                'ends_on' => $period['ends_on']->toDateString(),
                'renewal_due_on' => $period['ends_on']->toDateString(),
                'retention_until' => $period['retention_until']->toDateString(),
                'lapsed_at' => null,
            ])->save();

            $this->recordEvent(
                $membership,
                $renewKey,
                MembershipLifecycleEvent::TYPE_RENEWED,
                [
                    'payment_id' => $payment->id,
                    'mode' => $period['mode'],
                    'ends_on' => $period['ends_on']->toDateString(),
                ],
            );

            $reactivated = false;
            if ($wasPastGrace && $membership->profile_id) {
                $reactivated = $this->reactivateProfileAfterRenewal(
                    Profile::query()->whereKey($membership->profile_id)->lockForUpdate()->firstOrFail(),
                    $membership,
                    $payment,
                );
            }

            $this->auditLogger->log(
                action: 'membership.renewed',
                subject: $membership,
                before: $before,
                after: [
                    'status' => $membership->status,
                    'starts_on' => $membership->starts_on?->toDateString(),
                    'ends_on' => $membership->ends_on?->toDateString(),
                    'retention_until' => $membership->retention_until?->toDateString(),
                    'payment_id' => $payment->id,
                    'reactivated' => $reactivated,
                    'system' => true,
                ],
                actor: null,
            );

            return $membership->fresh() ?? $membership;
        });
    }

    /**
     * Process overdue deactivations and configurable renewal reminders.
     * Safe to run repeatedly; uses durable unique event keys.
     *
     * @return array{deactivated: int, reminders: int, skipped: int}
     */
    public function processDueLifecycle(?CarbonInterface $on = null): array
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();
        $deactivated = 0;
        $reminders = 0;
        $skipped = 0;

        Membership::query()
            ->whereNotNull('ends_on')
            ->whereIn('status', ['active', 'pending_renewal', 'lapsed'])
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($on, &$deactivated, &$reminders, &$skipped): void {
                foreach ($chunk as $membership) {
                    /** @var Membership $membership */
                    try {
                        $result = $this->processMembershipLifecycle($membership->id, $on);
                        $deactivated += $result['deactivated'] ? 1 : 0;
                        $reminders += $result['reminders'];
                        $skipped += $result['skipped'] ? 1 : 0;
                    } catch (Throwable $e) {
                        Log::error('Membership lifecycle processing failed.', [
                            'membership_id' => $membership->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return [
            'deactivated' => $deactivated,
            'reminders' => $reminders,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{deactivated: bool, reminders: int, skipped: bool}
     */
    public function processMembershipLifecycle(int $membershipId, ?CarbonInterface $on = null): array
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        return DB::transaction(function () use ($membershipId, $on) {
            /** @var Membership|null $membership */
            $membership = Membership::query()->whereKey($membershipId)->lockForUpdate()->first();
            if (! $membership instanceof Membership) {
                return ['deactivated' => false, 'reminders' => 0, 'skipped' => true];
            }

            // Re-check after lock: a concurrent settled renewal must win over stale deactivation.
            $membership->refresh();
            if ($membership->ends_on !== null && ! $this->isPastGracePeriod($membership, $on)) {
                $reminders = $this->sendDueReminders($membership, $on);

                return ['deactivated' => false, 'reminders' => $reminders, 'skipped' => false];
            }

            $deactivated = $this->deactivateIfEligible($membership, $on);
            $reminders = $this->sendDueReminders($membership->fresh() ?? $membership, $on);

            return ['deactivated' => $deactivated, 'reminders' => $reminders, 'skipped' => false];
        });
    }

    public function deactivateIfEligible(Membership $membership, ?CarbonInterface $on = null): bool
    {
        $on = ($on ?? $this->today())->copy()->startOfDay();

        if (! $this->isPastGracePeriod($membership, $on)) {
            return false;
        }

        // Authoritative re-check: active paid period / grace means skip.
        if ($membership->ends_on !== null && ! $this->isPastGracePeriod($membership->fresh() ?? $membership, $on)) {
            return false;
        }

        $endsKey = $membership->ends_on?->toDateString() ?? 'none';
        $eventKey = 'deactivated:ends_on:'.$endsKey;
        if ($this->eventExists($membership->id, $eventKey)) {
            return false;
        }

        $profile = $membership->profile_id
            ? Profile::query()->whereKey($membership->profile_id)->lockForUpdate()->first()
            : null;

        $beforeMembership = [
            'status' => $membership->status,
            'lapsed_at' => $membership->lapsed_at?->toIso8601String(),
        ];
        $beforeProfile = $profile ? [
            'status' => $profile->status,
            'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
        ] : null;

        $membership->forceFill([
            'status' => 'lapsed',
            'lapsed_at' => $membership->lapsed_at ?? now(),
            'retention_until' => $membership->retention_until
                ?? ($membership->ends_on !== null
                    ? $this->computeRetentionUntil($membership->ends_on)->toDateString()
                    : null),
        ])->save();

        if ($profile instanceof Profile) {
            // Do not override independent administrative suspension.
            if ($profile->status !== 'suspended' && $profile->suspended_at === null) {
                $profile->forceFill([
                    'status' => 'inactive',
                    'unpublished_at' => $profile->unpublished_at ?? now(),
                ])->save();
            }
        }

        $this->recordEvent(
            $membership,
            $eventKey,
            MembershipLifecycleEvent::TYPE_DEACTIVATED,
            [
                'ends_on' => $endsKey,
                'grace_ends_on' => $this->graceEndsOn($membership)?->toDateString(),
                'retention_until' => $membership->retention_until?->toDateString(),
                'profile_id' => $profile?->id,
            ],
        );

        $this->auditLogger->log(
            action: 'membership.deactivated',
            subject: $membership,
            before: [
                'membership' => $beforeMembership,
                'profile' => $beforeProfile,
            ],
            after: [
                'membership_status' => $membership->status,
                'profile_status' => $profile?->fresh()?->status,
                'system' => true,
            ],
            actor: null,
        );

        return true;
    }

    private function reactivateProfileAfterRenewal(Profile $profile, Membership $membership, Payment $payment): bool
    {
        // Never clear administrative suspension via ordinary renewal.
        if ($profile->status === 'suspended' || $profile->suspended_at !== null) {
            return false;
        }

        $reactivateKey = 'reactivated:payment:'.$payment->id;
        if ($this->eventExists($membership->id, $reactivateKey)) {
            return false;
        }

        $wasInactive = $profile->status === 'inactive' || $profile->unpublished_at !== null;

        if ($wasInactive || $profile->status !== 'published') {
            // Restore public visibility only when editorial publication remains valid
            // (had been published; not erased). Slug / media / links stay intact.
            if ($profile->published_at !== null && $profile->erasure_completed_at === null) {
                $profile->forceFill([
                    'status' => 'published',
                    'unpublished_at' => null,
                ])->save();
            }
        }

        $this->recordEvent(
            $membership,
            $reactivateKey,
            MembershipLifecycleEvent::TYPE_REACTIVATED,
            [
                'payment_id' => $payment->id,
                'profile_id' => $profile->id,
                'slug' => $profile->slug,
            ],
        );

        $this->auditLogger->log(
            action: 'membership.reactivated',
            subject: $membership,
            before: null,
            after: [
                'profile_id' => $profile->id,
                'payment_id' => $payment->id,
                'profile_status' => $profile->fresh()?->status,
                'system' => true,
            ],
            actor: null,
        );

        return true;
    }

    private function sendDueReminders(Membership $membership, CarbonInterface $on): int
    {
        if ($membership->ends_on === null) {
            return 0;
        }

        $sent = 0;
        $ends = $membership->ends_on->copy()->startOfDay();
        $daysUntil = (int) $on->diffInDays($ends, false);

        foreach ($this->reminderDaysBeforeExpiry() as $offset) {
            if ($daysUntil === $offset) {
                if ($this->dispatchReminder($membership, MembershipLifecycleEvent::TYPE_REMINDER_BEFORE, $offset, $ends)) {
                    $sent++;
                }
            }
        }

        $daysAfter = (int) $ends->diffInDays($on, false);
        foreach ($this->reminderDaysAfterExpiry() as $offset) {
            if ($daysAfter === $offset && $on->gt($ends)) {
                if ($this->dispatchReminder($membership, MembershipLifecycleEvent::TYPE_REMINDER_AFTER, $offset, $ends)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function dispatchReminder(
        Membership $membership,
        string $eventType,
        int $offsetDays,
        CarbonInterface $endsOn,
    ): bool {
        $eventKey = sprintf(
            '%s:%d:ends_on:%s',
            $eventType,
            $offsetDays,
            $endsOn->toDateString(),
        );

        // Caller holds a membership row lock; check-then-send-then-record avoids
        // permanently suppressing retries after a transient mail failure.
        if ($this->eventExists($membership->id, $eventKey)) {
            return false;
        }

        $profile = $membership->profile;
        $user = $profile?->user;
        $email = $user?->email;

        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // No deliverable address — claim so we do not retry forever.
            $this->recordEvent($membership, $eventKey, $eventType, [
                'offset_days' => $offsetDays,
                'ends_on' => $endsOn->toDateString(),
                'provisional_copy' => true,
                'delivery' => 'skipped_invalid_email',
            ]);
            Log::warning('Membership renewal reminder skipped: no valid member email.', [
                'membership_id' => $membership->id,
                'event_key' => $eventKey,
            ]);

            return true;
        }

        try {
            $this->deliverRenewalReminderMail(
                $email,
                $membership->fresh() ?? $membership,
                $eventType,
                $offsetDays,
            );
        } catch (Throwable $e) {
            // Do not claim the event — next scheduler run may retry safely.
            Log::error('Membership renewal reminder mail failed; will retry on next run.', [
                'membership_id' => $membership->id,
                'event_key' => $eventKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $recorded = $this->recordEvent($membership, $eventKey, $eventType, [
            'offset_days' => $offsetDays,
            'ends_on' => $endsOn->toDateString(),
            'provisional_copy' => true,
            'delivery' => 'sent',
        ]);

        if (! $recorded) {
            // Concurrent winner already recorded after its own successful send.
            return false;
        }

        $this->auditLogger->log(
            action: 'membership.reminder_sent',
            subject: $membership,
            before: null,
            after: [
                'event_key' => $eventKey,
                'event_type' => $eventType,
                'offset_days' => $offsetDays,
                'system' => true,
            ],
            actor: null,
        );

        return true;
    }

    /**
     * Isolated for tests: transient delivery failures must not claim the reminder event.
     */
    protected function deliverRenewalReminderMail(
        string $email,
        Membership $membership,
        string $eventType,
        int $offsetDays,
    ): void {
        Mail::to($email)->send(new MembershipRenewalReminderMail(
            membership: $membership,
            eventType: $eventType,
            offsetDays: $offsetDays,
        ));
    }

    /**
     * Successful renewal date for period calculation (business timezone calendar day).
     */
    private function renewalReferenceDay(Payment $payment): Carbon
    {
        if ($payment->paid_at !== null) {
            return Carbon::parse($payment->paid_at)->timezone($this->businessTimezone())->startOfDay();
        }

        return $this->today();
    }

    private function eventExists(int $membershipId, string $eventKey): bool
    {
        return MembershipLifecycleEvent::query()
            ->where('membership_id', $membershipId)
            ->where('event_key', $eventKey)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordEvent(
        Membership $membership,
        string $eventKey,
        string $eventType,
        array $meta = [],
    ): bool {
        try {
            MembershipLifecycleEvent::query()->create([
                'membership_id' => $membership->id,
                'event_key' => $eventKey,
                'event_type' => $eventType,
                'recorded_at' => now(),
                'meta' => $meta,
            ]);

            return true;
        } catch (Throwable $e) {
            // Unique violation = concurrent claim already won.
            if ($this->eventExists($membership->id, $eventKey)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @return list<int>
     */
    private function normalizeDayOffsets(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $v) {
            if (is_numeric($v)) {
                $out[] = (int) $v;
            }
        }

        return array_values(array_unique($out));
    }
}

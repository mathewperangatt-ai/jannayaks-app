<?php

namespace App\Services;

use App\Models\InMemoriamProfile;
use App\Models\Payment;
use App\Models\User;
use App\Support\PricingAmounts;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InMemoriamLifecycleService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private InMemoriamUrlService $urls,
    ) {}

    public function businessTimezone(): string
    {
        return (string) config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata');
    }

    public function businessToday(?CarbonInterface $at = null): Carbon
    {
        $at ??= now();

        return Carbon::parse($at->toDateTimeString(), $at->timezone ?? config('app.timezone'))
            ->timezone($this->businessTimezone())
            ->startOfDay();
    }

    public function hostingYears(): int
    {
        return max(1, (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 5));
    }

    public function isPubliclyVisible(InMemoriamProfile $profile, ?CarbonInterface $at = null): bool
    {
        if ($profile->status !== InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED) {
            return false;
        }

        if ($profile->published_at === null
            || $profile->hosting_starts_on === null
            || $profile->hosting_ends_on === null) {
            return false;
        }

        $today = $this->businessToday($at);
        $starts = Carbon::parse($profile->hosting_starts_on->toDateString(), $this->businessTimezone())->startOfDay();
        $ends = Carbon::parse($profile->hosting_ends_on->toDateString(), $this->businessTimezone())->startOfDay();

        return $today->greaterThanOrEqualTo($starts) && $today->lessThanOrEqualTo($ends);
    }

    public function assertEditableByStaff(InMemoriamProfile $profile, User $actor): void
    {
        if ($profile->is_sealed) {
            throw ValidationException::withMessages([
                'memorial' => 'This memorial is sealed. Routine edits are not permitted.',
            ]);
        }

        if (! $actor->canManageEditorial()) {
            throw ValidationException::withMessages([
                'memorial' => 'You are not allowed to edit this memorial.',
            ]);
        }
    }

    /**
     * Record offline package payment on the memorial (no Razorpay).
     *
     * @throws ValidationException
     */
    public function recordOfflinePackagePaid(InMemoriamProfile $profile, User $actor, ?string $note = null): InMemoriamProfile
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'payment' => 'Only Admin staff may record offline In Memoriam payment.',
            ]);
        }

        return DB::transaction(function () use ($profile, $actor, $note): InMemoriamProfile {
            /** @var InMemoriamProfile $locked */
            $locked = InMemoriamProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_sealed && $locked->status === InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED) {
                throw ValidationException::withMessages([
                    'payment' => 'Published sealed memorials do not accept a new package payment record here.',
                ]);
            }

            $pricing = PricingAmounts::forInMemoriam5yr();
            $before = [
                'commission_paid_at' => optional($locked->commission_paid_at)?->toIso8601String(),
                'status' => $locked->status,
            ];

            $base = PricingAmounts::paiseToDecimalString((int) $pricing['base_paise']);
            $gst = PricingAmounts::paiseToDecimalString((int) $pricing['gst_paise']);
            $total = PricingAmounts::paiseToDecimalString((int) $pricing['amount_incl_paise']);

            $locked->forceFill([
                'commission_amount' => $base,
                'commission_gst_amount' => $gst,
                'commission_currency' => PricingAmounts::CURRENCY,
                'commission_paid_at' => $locked->commission_paid_at ?? now(),
                'status' => in_array($locked->status, [
                    InMemoriamProfile::STATUS_DRAFT,
                    InMemoriamProfile::STATUS_PAYMENT_PENDING,
                ], true)
                    ? InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW
                    : $locked->status,
            ])->save();

            Payment::query()->create([
                'in_memoriam_profile_id' => $locked->id,
                'transaction_reference' => 'JNK-IM-OFF-'.$locked->id.'-'.Str::upper(Str::random(8)),
                'gateway' => Payment::GATEWAY_MANUAL,
                'item_type' => Payment::ITEM_IN_MEMORIAM,
                'amount' => $total,
                'currency' => PricingAmounts::CURRENCY,
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'event_type' => 'in_memoriam_package',
                'base_amount' => $base,
                'taxable_amount' => $base,
                'gst_rate_percent' => $pricing['gst_rate_percent'],
                'cgst_amount' => $pricing['cgst_paise'] !== null
                    ? PricingAmounts::paiseToDecimalString((int) $pricing['cgst_paise'])
                    : null,
                'sgst_amount' => $pricing['sgst_paise'] !== null
                    ? PricingAmounts::paiseToDecimalString((int) $pricing['sgst_paise'])
                    : null,
                'igst_amount' => $pricing['igst_paise'] !== null
                    ? PricingAmounts::paiseToDecimalString((int) $pricing['igst_paise'])
                    : null,
            ]);

            $this->audit->log(
                action: 'in_memoriam.offline_payment_recorded',
                subject: $locked,
                before: $before,
                after: [
                    'commission_paid_at' => optional($locked->commission_paid_at)?->toIso8601String(),
                    'status' => $locked->status,
                    'amount_incl_paise' => (int) $pricing['amount_incl_paise'],
                    'note_present' => filled($note),
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @throws ValidationException
     */
    public function publish(InMemoriamProfile $profile, User $actor, ?string $slug = null): InMemoriamProfile
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'publish' => 'Only Admin staff may publish an In Memoriam memorial.',
            ]);
        }

        return DB::transaction(function () use ($profile, $actor, $slug): InMemoriamProfile {
            /** @var InMemoriamProfile $locked */
            $locked = InMemoriamProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED && $locked->is_sealed) {
                throw ValidationException::withMessages([
                    'publish' => 'This memorial is already published and sealed.',
                ]);
            }

            if (! $locked->isPackagePaid()) {
                throw ValidationException::withMessages([
                    'publish' => 'Record offline package payment before publishing.',
                ]);
            }

            if ($locked->status === InMemoriamProfile::STATUS_REJECTED) {
                throw ValidationException::withMessages([
                    'publish' => 'A rejected memorial cannot be published.',
                ]);
            }

            if (filled($slug)) {
                $this->urls->assignSlug($locked, $slug, $actor);
                $locked->refresh();
            } elseif (! filled($locked->slug)) {
                throw ValidationException::withMessages([
                    'publish' => 'A public memorial URL slug is required before publication.',
                ]);
            }

            $today = $this->businessToday();
            $ends = $today->copy()->addYears($this->hostingYears());

            $before = [
                'status' => $locked->status,
                'is_sealed' => $locked->is_sealed,
            ];

            $locked->forceFill([
                'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
                'published_at' => now(),
                'hosting_starts_on' => $today->toDateString(),
                'hosting_ends_on' => $ends->toDateString(),
                'renewal_due_on' => $ends->toDateString(),
                'is_sealed' => true,
                'editorial_reviewed_at' => $locked->editorial_reviewed_at ?? now(),
            ])->save();

            $this->audit->log(
                action: 'in_memoriam.published',
                subject: $locked,
                before: $before,
                after: [
                    'status' => $locked->status,
                    'is_sealed' => true,
                    'hosting_starts_on' => $locked->hosting_starts_on?->toDateString(),
                    'hosting_ends_on' => $locked->hosting_ends_on?->toDateString(),
                    'slug' => $locked->slug,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function applyExceptionalCorrection(
        InMemoriamProfile $profile,
        User $actor,
        array $attributes,
        string $notes,
    ): InMemoriamProfile {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'correction' => 'Only Admin staff may perform exceptional sealed corrections.',
            ]);
        }

        $notes = trim($notes);
        if ($notes === '') {
            throw ValidationException::withMessages([
                'correction' => 'Correction notes are required for audit.',
            ]);
        }

        $allowed = [
            'deceased_full_name',
            'deceased_display_name',
            'deceased_gender',
            'deceased_date_of_birth',
            'deceased_place_of_birth',
            'deceased_date_of_death',
            'deceased_place_of_death',
            'profession',
            'bio_headline',
            'commissioner_contact_name',
            'commissioner_relation',
            'commissioner_display_consent',
        ];
        $filtered = array_intersect_key($attributes, array_flip($allowed));
        if ($filtered === []) {
            throw ValidationException::withMessages([
                'correction' => 'No allowed correction fields were provided.',
            ]);
        }

        return DB::transaction(function () use ($profile, $actor, $filtered, $notes): InMemoriamProfile {
            /** @var InMemoriamProfile $locked */
            $locked = InMemoriamProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_sealed || $locked->status !== InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED) {
                throw ValidationException::withMessages([
                    'correction' => 'Exceptional correction applies only to sealed published memorials.',
                ]);
            }

            $before = $locked->only(array_keys($filtered));
            $locked->forceFill($filtered + [
                'last_admin_corrected_at' => now(),
                'last_admin_corrected_by_user_id' => $actor->id,
                'admin_correction_notes' => mb_substr($notes, 0, 5000),
                'is_sealed' => true,
                'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
            ])->save();

            $this->audit->log(
                action: 'in_memoriam.admin_exceptional_correction',
                subject: $locked,
                before: $before,
                after: $locked->only(array_keys($filtered)) + [
                    'admin_correction_notes' => $locked->admin_correction_notes,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array{verification_status: string, verification_method?: ?string, verification_notes?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function recordVerification(InMemoriamProfile $profile, User $actor, array $data): InMemoriamProfile
    {
        if (! $actor->canManageEditorial()) {
            throw ValidationException::withMessages([
                'verification' => 'You are not allowed to record death verification.',
            ]);
        }

        if ($profile->is_sealed && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'verification' => 'Sealed memorials require Admin exceptional correction for verification changes.',
            ]);
        }

        $status = (string) ($data['verification_status'] ?? '');
        $allowed = [
            InMemoriamProfile::VERIFICATION_UNVERIFIED,
            InMemoriamProfile::VERIFICATION_VERIFIED,
            InMemoriamProfile::VERIFICATION_COULD_NOT_VERIFY,
            InMemoriamProfile::VERIFICATION_WAIVED,
        ];
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'verification_status' => 'Invalid verification status.',
            ]);
        }

        $method = $data['verification_method'] ?? null;
        if (! in_array($method, [null, 'death_certificate_inspection', 'official_records', 'both', 'other'], true)) {
            throw ValidationException::withMessages([
                'verification_method' => 'Invalid verification method.',
            ]);
        }

        $notes = isset($data['verification_notes'])
            ? mb_substr(trim((string) $data['verification_notes']), 0, 2000)
            : null;

        return DB::transaction(function () use ($profile, $actor, $status, $method, $notes): InMemoriamProfile {
            /** @var InMemoriamProfile $locked */
            $locked = InMemoriamProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $before = [
                'verification_status' => $locked->verification_status,
                'verification_method' => $locked->verification_method,
            ];

            $locked->forceFill([
                'verification_status' => $status,
                'verification_method' => $method,
                'verification_notes' => $notes !== '' ? $notes : null,
                'verified_at' => in_array($status, [
                    InMemoriamProfile::VERIFICATION_VERIFIED,
                    InMemoriamProfile::VERIFICATION_COULD_NOT_VERIFY,
                    InMemoriamProfile::VERIFICATION_WAIVED,
                ], true) ? now() : null,
            ])->save();

            $this->audit->log(
                action: 'in_memoriam.verification_recorded',
                subject: $locked,
                before: $before,
                after: [
                    'verification_status' => $locked->verification_status,
                    'verification_method' => $locked->verification_method,
                    'verification_notes_present' => filled($locked->verification_notes),
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }
}

<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialMalayalamConsent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Malayalam approval and consent for a profile whose English text is already
 * published. This path does not replace the English customer approval and
 * does not call the living-profile publication workflow.
 */
class MalayalamConsentService
{
    public function __construct(
        private StaffAuditLogger $auditLogger,
    ) {}

    public function approveForCustomerConsent(Application $application, EditorialContent $malayalam, User $actor): EditorialContent
    {
        if (! $actor->canManageEditorial() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only editorial staff may approve Malayalam for customer consent.');
        }

        return DB::transaction(function () use ($application, $malayalam, $actor) {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            /** @var EditorialContent $locked */
            $locked = EditorialContent::query()->whereKey($malayalam->id)->lockForUpdate()->firstOrFail();
            $english = $this->publishedEnglish($lockedApplication);

            $this->assertPairedToPublishedEnglish($lockedApplication, $locked, $english);

            if ($locked->status === EditorialContent::STATUS_APPROVED) {
                return $locked;
            }

            if ($locked->status !== EditorialContent::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only a Malayalam draft paired to the published English version can be approved.');
            }

            $before = [
                'status' => $locked->status,
                'published_english_editorial_content_id' => $lockedApplication->published_english_editorial_content_id,
                'customer_approved_english_editorial_content_id' => $lockedApplication->customer_approved_english_editorial_content_id,
                'customer_approved_at' => $lockedApplication->customer_approved_at?->toIso8601String(),
            ];

            $locked->forceFill([
                'status' => EditorialContent::STATUS_APPROVED,
                'reviewed_by_id' => $actor->id,
            ])->save();

            $lockedApplication->refresh();
            $this->assertEnglishPublicationUntouched($lockedApplication, $english, $before);

            $this->auditLogger->log(
                action: 'editorial.malayalam_approved_for_consent',
                subject: $locked,
                before: $before,
                after: [
                    'status' => $locked->status,
                    'content_sha256' => EditorialMalayalamConsent::fingerprint($locked),
                    'version_number' => $locked->version_number,
                    'source_editorial_content_id' => $locked->source_editorial_content_id,
                    'published_english_editorial_content_id' => $lockedApplication->published_english_editorial_content_id,
                    'customer_approved_english_editorial_content_id' => $lockedApplication->customer_approved_english_editorial_content_id,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    public function recordCustomerConsent(Application $application, User $member, int $malayalamEditorialContentId): EditorialMalayalamConsent
    {
        if ((int) $application->user_id !== (int) $member->id) {
            throw new InvalidArgumentException('Only the profile owner may consent to the Malayalam text.');
        }

        return DB::transaction(function () use ($application, $member, $malayalamEditorialContentId) {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            /** @var EditorialContent $malayalam */
            $malayalam = EditorialContent::query()->whereKey($malayalamEditorialContentId)->lockForUpdate()->firstOrFail();
            $english = $this->publishedEnglish($lockedApplication);
            $this->assertPairedToPublishedEnglish($lockedApplication, $malayalam, $english);

            if ($malayalam->status !== EditorialContent::STATUS_APPROVED) {
                throw new InvalidArgumentException('Malayalam must be editorially approved before the customer can consent.');
            }

            return $this->storeActiveConsent($lockedApplication, $member, $malayalam);
        });
    }

    /**
     * Records consent for Malayalam the customer has just reviewed on a
     * maintenance preview. The replacement English version is not published
     * yet, so this does not use the published-English pairing check. It still
     * stores a separate consent row for the exact Malayalam text.
     */
    public function recordReviewedMalayalamConsent(Application $application, User $member, EditorialContent $malayalam): EditorialMalayalamConsent
    {
        if ((int) $application->user_id !== (int) $member->id) {
            throw new InvalidArgumentException('Only the profile owner may consent to the Malayalam text.');
        }

        return DB::transaction(function () use ($application, $member, $malayalam) {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            /** @var EditorialContent $locked */
            $locked = EditorialContent::query()->whereKey($malayalam->id)->lockForUpdate()->firstOrFail();

            if ($locked->language !== EditorialContent::LANGUAGE_ML || (int) $locked->profile_id !== (int) $lockedApplication->profile_id) {
                throw new InvalidArgumentException('The Malayalam text does not belong to this profile.');
            }

            if ($locked->status !== EditorialContent::STATUS_APPROVED) {
                throw new InvalidArgumentException('Malayalam must be editorially approved before the customer can consent.');
            }

            return $this->storeActiveConsent($lockedApplication, $member, $locked);
        });
    }

    public function attachToPublishedProfile(Application $application, User $actor): Application
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only an authorised Admin may attach Malayalam to a published profile.');
        }

        return DB::transaction(function () use ($application, $actor) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Application::STATUS_PUBLISHED) {
                throw new InvalidArgumentException('Malayalam can be attached only to an already published profile.');
            }

            $english = $this->publishedEnglish($locked);
            $consent = $this->activeConsent($locked);
            if (! $consent instanceof EditorialMalayalamConsent) {
                throw new InvalidArgumentException('Explicit customer consent for the Malayalam text is required.');
            }

            /** @var EditorialContent $malayalam */
            $malayalam = EditorialContent::query()->whereKey($consent->malayalam_editorial_content_id)->lockForUpdate()->firstOrFail();
            $this->assertPairedToPublishedEnglish($locked, $malayalam, $english);

            if ($malayalam->status !== EditorialContent::STATUS_APPROVED) {
                throw new InvalidArgumentException('Malayalam must be editorially approved before it can be attached.');
            }

            if (! $consent->matches($malayalam)) {
                throw new InvalidArgumentException('The Malayalam consent is stale and does not match the current text.');
            }

            $before = [
                'status' => $locked->status,
                'published_english_editorial_content_id' => $locked->published_english_editorial_content_id,
                'published_malayalam_editorial_content_id' => $locked->published_malayalam_editorial_content_id,
                'customer_approved_english_editorial_content_id' => $locked->customer_approved_english_editorial_content_id,
                'customer_approved_at' => $locked->customer_approved_at?->toIso8601String(),
                'payment_status' => $locked->payment_status,
            ];

            $locked->forceFill([
                'published_malayalam_editorial_content_id' => $malayalam->id,
            ])->save();

            $this->assertEnglishPublicationUntouched($locked, $english, $before);

            $this->auditLogger->log(
                action: 'editorial.malayalam_attached',
                subject: $locked,
                before: $before,
                after: [
                    'status' => $locked->status,
                    'published_english_editorial_content_id' => $locked->published_english_editorial_content_id,
                    'published_malayalam_editorial_content_id' => $locked->published_malayalam_editorial_content_id,
                    'malayalam_consent_id' => $consent->id,
                    'content_sha256' => $consent->content_sha256,
                    'payment_status' => $locked->payment_status,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    public function consentCandidate(Application $application): ?EditorialContent
    {
        if ($application->status !== Application::STATUS_PUBLISHED || $application->published_english_editorial_content_id === null) {
            return null;
        }

        $malayalam = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->where('source_editorial_content_id', $application->published_english_editorial_content_id)
            ->orderByDesc('version_number')
            ->first();

        return $malayalam instanceof EditorialContent ? $malayalam : null;
    }

    public function hasCurrentCustomerConsent(Application $application): bool
    {
        $candidate = $this->consentCandidate($application);
        $consent = $this->activeConsent($application);

        return $candidate instanceof EditorialContent
            && $consent instanceof EditorialMalayalamConsent
            && $consent->matches($candidate);
    }

    /**
     * Initial and replacement publication may bind Malayalam only when the
     * active consent matches that exact text.
     *
     * The only exception is an application whose source_method is
     * admin_test_demo. DemoProfilesSeeder sets that source explicitly.
     * An internal email address does not grant the exception.
     *
     * Profiles that were published before this consent table, and that have
     * no consent row at all, stay visible through the public resolver.
     * A consent row that does not match the bound text hides Malayalam.
     */
    public function allowsPublicationBinding(Application $application, EditorialContent $malayalam): bool
    {
        if ($application->source_method === 'admin_test_demo') {
            return true;
        }

        $consent = $this->activeConsent($application);

        return $consent instanceof EditorialMalayalamConsent && $consent->matches($malayalam);
    }

    /**
     * @return array{key: string, message: string, needs_action: bool}|null
     */
    public function customerFacingMalayalamState(Application $application): ?array
    {
        if ($application->status !== Application::STATUS_PUBLISHED || $application->published_english_editorial_content_id === null) {
            return null;
        }

        $candidate = $this->consentCandidate($application);
        $active = $this->activeConsent($application);
        $history = EditorialMalayalamConsent::query()->where('application_id', $application->id)->exists();
        $boundId = $application->published_malayalam_editorial_content_id;

        if ($candidate instanceof EditorialContent && $active instanceof EditorialMalayalamConsent && $active->matches($candidate)) {
            if ((int) $boundId === (int) $candidate->id) {
                return [
                    'key' => 'published',
                    'message' => 'Malayalam is published on your profile.',
                    'needs_action' => false,
                ];
            }

            return [
                'key' => 'consent_recorded',
                'message' => 'Your consent is recorded. Malayalam will be published after the editorial team attaches this version.',
                'needs_action' => false,
            ];
        }

        if ($candidate instanceof EditorialContent) {
            $changed = $history && ! ($active instanceof EditorialMalayalamConsent && $active->matches($candidate));

            return [
                'key' => 'awaiting_consent',
                'message' => $changed
                    ? 'The Malayalam text has changed. Your earlier consent does not cover this version. Please review it again.'
                    : 'Malayalam is ready for your consent. This is separate from your English approval.',
                'needs_action' => true,
            ];
        }

        if ($boundId !== null && ! $history) {
            return [
                'key' => 'published',
                'message' => 'Malayalam is published on your profile.',
                'needs_action' => false,
            ];
        }

        $draft = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('status', EditorialContent::STATUS_DRAFT)
            ->where('source_editorial_content_id', $application->published_english_editorial_content_id)
            ->exists();

        if ($draft) {
            return [
                'key' => 'awaiting_staff',
                'message' => 'Malayalam is being prepared. It is not public until staff review, your consent, and publication.',
                'needs_action' => false,
            ];
        }

        if ($history) {
            return [
                'key' => 'stale',
                'message' => 'Malayalam is not currently public because the consented text changed. The editorial team needs to prepare the current version.',
                'needs_action' => false,
            ];
        }

        return null;
    }

    /**
     * Each published application is placed in at most one Malayalam queue.
     *
     * @return array{awaiting_staff: list<int>, awaiting_consent: list<int>, consent_recorded: list<int>, stale_consent: list<int>}
     */
    public function attentionApplicationIds(): array
    {
        $buckets = [
            'awaiting_staff' => [],
            'awaiting_consent' => [],
            'consent_recorded' => [],
            'stale_consent' => [],
        ];

        $profileIds = EditorialContent::query()
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->distinct()
            ->pluck('profile_id');
        $consentApplicationIds = EditorialMalayalamConsent::query()->distinct()->pluck('application_id');

        $applications = Application::query()
            ->where('status', Application::STATUS_PUBLISHED)
            ->whereNotNull('published_english_editorial_content_id')
            ->where(function ($query) use ($profileIds, $consentApplicationIds): void {
                $query->whereIn('profile_id', $profileIds)
                    ->orWhereIn('id', $consentApplicationIds);
            })
            ->get();

        foreach ($applications as $application) {
            $state = $this->customerFacingMalayalamState($application);
            $key = $state['key'] ?? null;
            if ($key === 'awaiting_staff') {
                $buckets['awaiting_staff'][] = (int) $application->id;
            } elseif ($key === 'awaiting_consent') {
                $buckets['awaiting_consent'][] = (int) $application->id;
            } elseif ($key === 'consent_recorded') {
                $buckets['consent_recorded'][] = (int) $application->id;
            } elseif ($key === 'stale') {
                $buckets['stale_consent'][] = (int) $application->id;
            } elseif ($application->published_malayalam_editorial_content_id !== null) {
                $bound = EditorialContent::query()->find($application->published_malayalam_editorial_content_id);
                $active = $this->activeConsent($application);
                $history = EditorialMalayalamConsent::query()->where('application_id', $application->id)->exists();
                if ($history && (! $bound instanceof EditorialContent || ! $active instanceof EditorialMalayalamConsent || ! $active->matches($bound))) {
                    $buckets['stale_consent'][] = (int) $application->id;
                }
            }
        }

        return $buckets;
    }

    public function invalidateIfContentChanged(EditorialContent $content, ?User $actor = null, string $reason = 'Malayalam text changed after customer consent.'): void
    {
        if ($content->language !== EditorialContent::LANGUAGE_ML) {
            return;
        }

        DB::transaction(function () use ($content, $actor, $reason): void {
            $consents = EditorialMalayalamConsent::query()
                ->where('malayalam_editorial_content_id', $content->id)
                ->whereNull('invalidated_at')
                ->lockForUpdate()
                ->get();

            $invalidatedAny = false;
            foreach ($consents as $consent) {
                if ($consent->matches($content)) {
                    continue;
                }

                $consent->forceFill([
                    'invalidated_at' => now(),
                    'invalidation_reason' => mb_substr($reason, 0, 255),
                ])->save();
                $invalidatedAny = true;

                $this->auditLogger->log(
                    action: 'editorial.malayalam_consent_invalidated',
                    subject: $consent,
                    after: [
                        'malayalam_editorial_content_id' => $content->id,
                        'reason' => $reason,
                    ],
                    actor: $actor,
                );
            }

            if (! $invalidatedAny) {
                return;
            }

            $applications = Application::query()
                ->where('published_malayalam_editorial_content_id', $content->id)
                ->lockForUpdate()
                ->get();

            foreach ($applications as $application) {
                $beforeId = $application->published_malayalam_editorial_content_id;
                $application->forceFill([
                    'published_malayalam_editorial_content_id' => null,
                ])->save();

                $this->auditLogger->log(
                    action: 'editorial.malayalam_binding_cleared',
                    subject: $application,
                    before: ['published_malayalam_editorial_content_id' => $beforeId],
                    after: [
                        'published_malayalam_editorial_content_id' => null,
                        'reason' => $reason,
                    ],
                    actor: $actor,
                );
            }
        });
    }

    private function storeActiveConsent(Application $application, User $member, EditorialContent $malayalam): EditorialMalayalamConsent
    {
        $fingerprint = EditorialMalayalamConsent::fingerprint($malayalam);
        $existing = $this->activeConsent($application);
        if ($existing instanceof EditorialMalayalamConsent && $existing->matches($malayalam)) {
            return $existing;
        }

        $this->invalidateActiveConsents($application, 'Superseded by a new Malayalam consent.', $member);

        $consent = EditorialMalayalamConsent::query()->create([
            'application_id' => $application->id,
            'profile_id' => $application->profile_id,
            'consented_by_user_id' => $member->id,
            'malayalam_editorial_content_id' => $malayalam->id,
            'content_sha256' => $fingerprint,
            'version_number' => $malayalam->version_number,
            'consented_at' => now(),
        ]);

        $this->auditLogger->log(
            action: 'editorial.malayalam_customer_consented',
            subject: $consent,
            after: [
                'application_id' => $application->id,
                'malayalam_editorial_content_id' => $malayalam->id,
                'version_number' => $malayalam->version_number,
                'content_sha256' => $fingerprint,
                'customer_approved_english_editorial_content_id' => $application->customer_approved_english_editorial_content_id,
            ],
            actor: $member,
        );

        return $consent;
    }

    private function publishedEnglish(Application $application): EditorialContent
    {
        if ($application->status !== Application::STATUS_PUBLISHED || $application->published_english_editorial_content_id === null) {
            throw new InvalidArgumentException('A published English version is required before Malayalam can be approved separately.');
        }

        $english = EditorialContent::query()->find($application->published_english_editorial_content_id);
        if (! $english instanceof EditorialContent
            || $english->language !== EditorialContent::LANGUAGE_EN
            || $english->status !== EditorialContent::STATUS_APPROVED
            || (int) $english->profile_id !== (int) $application->profile_id) {
            throw new InvalidArgumentException('The published English version is no longer the approved text for this profile.');
        }

        return $english;
    }

    private function assertPairedToPublishedEnglish(Application $application, EditorialContent $malayalam, EditorialContent $english): void
    {
        if ($malayalam->language !== EditorialContent::LANGUAGE_ML) {
            throw new InvalidArgumentException('Only Malayalam editorial content can use this approval.');
        }

        if ((int) $malayalam->profile_id !== (int) $application->profile_id) {
            throw new InvalidArgumentException('The Malayalam text does not belong to this profile.');
        }

        if ((int) $malayalam->source_editorial_content_id !== (int) $english->id) {
            throw new InvalidArgumentException('Malayalam must be paired to the English version currently published on the profile.');
        }
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertEnglishPublicationUntouched(Application $application, EditorialContent $english, array $before): void
    {
        if ((int) $application->published_english_editorial_content_id !== (int) $english->id
            || (int) $application->customer_approved_english_editorial_content_id !== (int) ($before['customer_approved_english_editorial_content_id'] ?? 0)
            || $application->status !== Application::STATUS_PUBLISHED
            || $english->fresh()?->status !== EditorialContent::STATUS_APPROVED) {
            throw new InvalidArgumentException('The published English profile must remain unchanged.');
        }
    }

    private function activeConsent(Application $application): ?EditorialMalayalamConsent
    {
        $consent = EditorialMalayalamConsent::query()
            ->where('application_id', $application->id)
            ->whereNull('invalidated_at')
            ->first();

        return $consent instanceof EditorialMalayalamConsent ? $consent : null;
    }

    private function invalidateActiveConsents(Application $application, string $reason, ?User $actor = null): void
    {
        $consents = EditorialMalayalamConsent::query()
            ->where('application_id', $application->id)
            ->whereNull('invalidated_at')
            ->lockForUpdate()
            ->get();

        foreach ($consents as $consent) {
            $consent->forceFill([
                'invalidated_at' => now(),
                'invalidation_reason' => mb_substr($reason, 0, 255),
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.malayalam_consent_invalidated',
                subject: $consent,
                after: [
                    'malayalam_editorial_content_id' => $consent->malayalam_editorial_content_id,
                    'reason' => $reason,
                ],
                actor: $actor,
            );
        }
    }
}

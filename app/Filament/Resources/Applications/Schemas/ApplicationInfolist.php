<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\MembershipLifecycleService;
use App\Services\ProfileUrlService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use App\Support\TierLabels;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            /*
             * Jannayaks Profile Workspace — admin mirrors the customer
             * architecture: Identity → Customer/contact → Workflow →
             * Revision requests → Editorial versions → read-only details.
             */
            Section::make('Profile identity')
                ->description('The customer\'s Jannayaks profile: reference number, photograph, tier, publication state and media.')
                ->schema([
                    ImageEntry::make('profile_primary_photo')
                        ->label('Profile photograph (approved / primary)')
                        ->circular()
                        ->height(96)
                        ->state(function (?Application $record): ?string {
                            $media = self::primaryPhoto($record);

                            return $media ? route('staff.profile-media.preview', ['media' => $media->id]) : null;
                        })
                        ->placeholder('No approved photograph yet'),
                    TextEntry::make('profile_display_name')
                        ->label('Display name')
                        ->state(fn (?Application $record): string => (string) ($record?->profile?->display_name ?: $record?->profile?->full_name ?? '—')),
                    TextEntry::make('profile_reference_code')
                        ->label('Profile reference')
                        ->badge()
                        ->color('primary')
                        ->copyable()
                        ->copyMessage('Reference number copied')
                        ->copyMessageDuration(1500)
                        ->state(fn (?Application $record): ?string => $record?->profile?->reference_code)
                        ->placeholder('—'),
                    TextEntry::make('profile_tier')
                        ->label('Tier')
                        ->badge()
                        ->color('primary')
                        ->state(fn (?Application $record): string => TierLabels::label((string) $record->package_tier)),
                    TextEntry::make('profile_publication')
                        ->label('Publication')
                        ->badge()
                        ->color(fn (?Application $record): string => $record?->profile !== null && $record->profile->isPubliclyListed() ? 'success' : 'gray')
                        ->state(function (?Application $record): string {
                            $profile = $record?->profile;

                            return $profile === null ? 'No profile yet' : (string) $profile->status;
                        }),
                    TextEntry::make('profile_public_url')
                        ->label('Public profile URL')
                        ->state(function (?Application $record): ?string {
                            $profile = $record?->profile;

                            return ($profile !== null && $profile->status === 'published' && filled($profile->slug))
                                ? app(ProfileUrlService::class)->canonicalPublicUrl($profile)
                                : null;
                        })
                        ->url(fn (?Application $record): ?string => ($record?->profile?->status === 'published' && filled($record->profile?->slug))
                            ? app(ProfileUrlService::class)->canonicalPublicUrl($record->profile)
                            : null, shouldOpenInNewTab: true)
                        ->placeholder('Not published yet'),
                    TextEntry::make('profile_slug')
                        ->label('Canonical slug')
                        ->state(fn (?Application $record): string => (string) ($record?->profile?->slug ?? '—')),
                    TextEntry::make('profile_media_summary')
                        ->label('Photographs')
                        ->state(function (?Application $record): string {
                            $media = $record?->profile?->media;

                            if ($media === null || $media->isEmpty()) {
                                return 'None uploaded';
                            }

                            return sprintf(
                                '%d approved · %d pending review · %d rejected',
                                $media->where('review_status', MediaItem::REVIEW_APPROVED)->count(),
                                $media->where('review_status', MediaItem::REVIEW_PENDING)->count(),
                                $media->where('review_status', MediaItem::REVIEW_REJECTED)->count(),
                            );
                        }),
                ])
                ->columns(3),
            Section::make('Customer & contact')
                ->schema([
                    TextEntry::make('id')->label('Application ID'),
                    TextEntry::make('full_name'),
                    TextEntry::make('preferred_display_name'),
                    TextEntry::make('user.email')
                        ->label('Account email')
                        ->visible(fn (?Application $record): bool => self::canSeeAccountEmail($record)),
                    TextEntry::make('preferred_contact_email')
                        ->label('Preferred contact email')
                        ->visible(fn (?Application $record): bool => self::canSeePrivateContact($record)),
                    TextEntry::make('preferred_contact_mobile')
                        ->label('Preferred contact mobile')
                        ->visible(fn (?Application $record): bool => self::canSeePrivateContact($record)),
                ])
                ->columns(2)
                ->collapsed(),
            Section::make('Workflow')
                ->schema([
                    TextEntry::make('workflow_wayfinding')
                        ->label('')
                        ->hiddenLabel()
                        ->state(fn (?Application $record): ?string => $record === null ? null : self::workflowBannerHtml($record))
                        ->html()
                        ->columnSpanFull(),
                    Section::make('Submission')
                        ->compact()
                        ->schema([
                            TextEntry::make('source_method'),
                            TextEntry::make('online_interview_completed_at')->dateTime()->placeholder('—'),
                            TextEntry::make('direct_submission_received_at')->dateTime()->placeholder('—'),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                    Section::make('Payment')
                        ->compact()
                        ->schema([
                            TextEntry::make('payment_status')
                                ->visible(fn (): bool => self::actor()?->canManageFinance() ?? false),
                            TextEntry::make('payment_settled_at')->dateTime()->placeholder('—'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                    Section::make('Editorial')
                        ->compact()
                        ->schema([
                            TextEntry::make('included_revision_rounds_used')
                                ->label('Included revision rounds used')
                                ->formatStateUsing(fn (?int $state): string => ((int) $state).' / 2'),
                        ])
                        ->columns(1)
                        ->columnSpanFull(),
                    Section::make('Customer')
                        ->compact()
                        ->schema([
                            TextEntry::make('customer_preview_released_at')->dateTime()->placeholder('—'),
                            TextEntry::make('preview_english_editorial_content_id')
                                ->label('Preview EN version')
                                ->formatStateUsing(fn ($state): string => filled($state) ? 'EN version #'.((int) $state) : '—')
                                ->placeholder('—')
                                ->color('primary')
                                ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                            TextEntry::make('preview_malayalam_editorial_content_id')
                                ->label('Preview ML version')
                                ->formatStateUsing(fn ($state): string => filled($state) ? 'ML version #'.((int) $state) : '—')
                                ->placeholder('—')
                                ->color('primary')
                                ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                            TextEntry::make('customer_approved_at')->dateTime()->placeholder('—'),
                            TextEntry::make('customer_approved_english_editorial_content_id')
                                ->label('Approved EN version')
                                ->formatStateUsing(fn ($state): string => filled($state) ? 'EN version #'.((int) $state) : '—')
                                ->placeholder('—')
                                ->color('primary')
                                ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                    Section::make('Publication')
                        ->compact()
                        ->schema([
                            TextEntry::make('published_english_editorial_content_id')
                                ->label('Published EN version')
                                ->formatStateUsing(fn ($state): string => filled($state) ? 'EN version #'.((int) $state) : 'Not published yet')
                                ->placeholder('Not published yet')
                                ->color('primary')
                                ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                            TextEntry::make('published_malayalam_editorial_content_id')
                                ->label('Published ML version')
                                ->formatStateUsing(fn ($state): string => filled($state) ? 'ML version #'.((int) $state) : 'Not published yet')
                                ->placeholder('Not published yet')
                                ->color('primary')
                                ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
            Section::make('Customer revision requests')
                ->description('Free-form change requests from the customer, pinned to the exact preview versions they reviewed. The customer does not edit editorial text directly — the editorial team processes each request. Open cycles are shown here; completed history is under "Completed request history".')
                ->schema([
                    self::revisionRequestEntry()
                        ->state(fn (?Application $record): \Illuminate\Support\Collection => $record === null
                            ? collect()
                            : EditorialRevisionRequest::query()
                                ->where('application_id', (int) $record->id)
                                ->whereIn('status', [
                                    EditorialRevisionRequest::STATUS_SUBMITTED,
                                    EditorialRevisionRequest::STATUS_IN_PROGRESS,
                                    EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW,
                                    EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED,
                                ])
                                ->orderByDesc('round_number')
                                ->orderByDesc('id')
                                ->get()),
                ])
                ->extraAttributes(['style' => 'border-left:3px solid #C0762E;background:#fffaf3;border-radius:0 10px 10px 0'])
                ->visible(fn (?Application $record): bool => EditorialRevisionRequest::query()
                    ->where('application_id', (int) $record?->id)
                    ->whereIn('status', [
                        EditorialRevisionRequest::STATUS_SUBMITTED,
                        EditorialRevisionRequest::STATUS_IN_PROGRESS,
                        EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW,
                        EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED,
                    ])
                    ->exists()),
            Section::make('Completed request history')
                ->description('Completed and cancelled revision/maintenance cycles for this profile, newest first. Bounded to the most recent 25 in this view; every record remains in the database and audit trail.')
                ->schema([
                    self::revisionRequestEntry()
                        ->state(fn (?Application $record): \Illuminate\Support\Collection => $record === null
                            ? collect()
                            : EditorialRevisionRequest::query()
                                ->where('application_id', (int) $record->id)
                                ->whereIn('status', [EditorialRevisionRequest::STATUS_COMPLETED, EditorialRevisionRequest::STATUS_CANCELLED])
                                ->orderByDesc('round_number')
                                ->orderByDesc('id')
                                ->limit(25)
                                ->get()),
                ])
                ->collapsed()
                ->visible(fn (?Application $record): bool => EditorialRevisionRequest::query()
                    ->where('application_id', (int) $record?->id)
                    ->whereIn('status', [EditorialRevisionRequest::STATUS_COMPLETED, EditorialRevisionRequest::STATUS_CANCELLED])
                    ->exists()),
            Section::make('Maintenance OLD → NEW comparison')
                ->description('Currently published Jannayaks content versus the latest Jannayaks-prepared proposed version, per language, with changed portions highlighted. The customer\'s request is shown separately above; the AI draft is source material only. Immutable versions are never altered.')
                ->schema([
                    TextEntry::make('maintenance_diff')
                        ->label('')
                        ->hiddenLabel()
                        ->state(fn (?Application $record): string => self::maintenanceDiffHtml($record))
                        ->html()
                        ->columnSpanFull(),
                ])
                ->visible(fn (?Application $record): bool => self::openMaintenanceRequest($record)?->resulting_english_editorial_content_id !== null),
            Section::make('Editorial versions')
                ->description('Every stored version for this profile: AI drafts, editorial versions, approved and archived states. Click a version to read it; the version pinned for the current workflow carries a badge.')
                ->schema([
                    RepeatableEntry::make('profile_editorial_versions')
                        ->label('')
                        ->state(fn (?Application $record): \Illuminate\Support\Collection => $record?->profile === null
                            ? collect()
                            : EditorialContent::query()
                                ->where('profile_id', $record->profile->id)
                                ->orderBy('language')
                                ->orderByDesc('version_number')
                                ->get())
                        ->schema([
                            TextEntry::make('id')
                                ->label('Version')
                                ->formatStateUsing(fn ($state): string => 'Open version #'.((int) $state).' →')
                                ->color('primary')
                                ->url(fn ($state): string => self::editorialContentViewUrl((int) $state), shouldOpenInNewTab: true),
                            TextEntry::make('language')
                                ->badge()
                                ->color(fn (string $state): string => $state === EditorialContent::LANGUAGE_ML ? 'warning' : 'info')
                                ->formatStateUsing(fn (string $state): string => $state === EditorialContent::LANGUAGE_ML ? 'Malayalam' : 'English'),
                            TextEntry::make('version_number')->label('Ver')->badge(),
                            TextEntry::make('status')->badge(),
                            IconEntry::make('ai_generated')->label('AI draft')->boolean(),
                            TextEntry::make('workflow_binding')
                                ->label('Bound as')
                                ->badge()
                                ->color(fn (?string $state): string => $state === 'Published' ? 'success' : 'warning')
                                ->state(fn (?EditorialContent $record): ?string => self::editorialBindingFor($record)),
                            TextEntry::make('title')->label('Title'),
                            TextEntry::make('summary')->label('Summary')->columnSpanFull(),
                        ])
                        ->columns(7)
                        ->columnSpanFull(),
                ])
                ->visible(fn (?Application $record): bool => $record?->profile !== null),
            Section::make('Profile URL (read-only)')
                ->description('Inspect canonical URL, history, tier, and QR availability. Support and Editors cannot reassign URLs.')
                ->schema([
                    TextEntry::make('profile.slug')
                        ->label('Canonical slug')
                        ->placeholder('—'),
                    TextEntry::make('profile_canonical_url')
                        ->label('Canonical public URL')
                        ->state(function (?Application $record): string {
                            if (! $record?->profile?->slug) {
                                return '—';
                            }

                            return app(ProfileUrlService::class)->canonicalPublicUrl($record->profile) ?? '—';
                        }),
                    TextEntry::make('package_tier')
                        ->label('URL tier rules')
                        ->formatStateUsing(function (?string $state): string {
                            $tier = strtolower((string) $state);
                            if (in_array($tier, ['accomplished', 'distinguished'], true)) {
                                return $tier.' — personal URL allowed';
                            }
                            if ($tier === 'emerging') {
                                return 'emerging — system 6-character URL only';
                            }

                            return $tier !== '' ? $tier : '—';
                        }),
                    TextEntry::make('profile_url_status')
                        ->label('URL / publication status')
                        ->state(function (?Application $record): string {
                            $profile = $record?->profile;
                            if (! $profile) {
                                return 'No linked profile';
                            }
                            if (! filled($profile->slug)) {
                                return 'No slug assigned';
                            }
                            if ($profile->status === 'published' && $profile->published_at && ! $profile->unpublished_at) {
                                return 'Published — public URL active';
                            }

                            return 'Slug reserved — not publicly exposed (status: '.$profile->status.')';
                        }),
                    TextEntry::make('profile_qr_availability')
                        ->label('QR availability')
                        ->state(function (?Application $record): string {
                            $profile = $record?->profile;
                            if (! $profile || ! filled($profile->slug)) {
                                return 'Unavailable';
                            }
                            if ($profile->status === 'published' && $profile->published_at && ! $profile->unpublished_at) {
                                return 'Available (encodes current /{slug})';
                            }

                            return 'Unavailable until published';
                        }),
                    TextEntry::make('profile_slug_history')
                        ->label('Historical URLs')
                        ->state(function (?Application $record): string {
                            $profile = $record?->profile;
                            if (! $profile) {
                                return '—';
                            }
                            $rows = $profile->slugRedirects()->orderByDesc('id')->limit(10)->get();
                            if ($rows->isEmpty()) {
                                return 'None';
                            }

                            return $rows->map(fn ($r) => $r->old_slug.' → '.$r->new_slug)->implode('; ');
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsed(),
            Section::make('Membership lifecycle (read-only)')
                ->description('Expiry, grace, deactivation, and retention. Staff cannot edit dates here. System cron and settled renewal payments are authoritative.')
                ->schema([
                    TextEntry::make('membership_status')
                        ->label('Membership status')
                        ->state(fn (?Application $record): string => (string) ($record?->profile?->membership?->status ?? '—')),
                    TextEntry::make('membership_phase')
                        ->label('Lifecycle phase')
                        ->state(function (?Application $record): string {
                            $m = $record?->profile?->membership;
                            if (! $m) {
                                return '—';
                            }

                            return app(MembershipLifecycleService::class)->lifecyclePhase($m);
                        }),
                    TextEntry::make('membership_starts_on')
                        ->label('Starts on')
                        ->state(fn (?Application $record): string => $record?->profile?->membership?->starts_on?->toDateString() ?? '—'),
                    TextEntry::make('membership_ends_on')
                        ->label('Expires on')
                        ->state(fn (?Application $record): string => $record?->profile?->membership?->ends_on?->toDateString() ?? '—'),
                    TextEntry::make('membership_grace_ends')
                        ->label('Grace ends on')
                        ->state(function (?Application $record): string {
                            $m = $record?->profile?->membership;
                            if (! $m) {
                                return '—';
                            }

                            return app(MembershipLifecycleService::class)->graceEndsOn($m)?->toDateString() ?? '—';
                        }),
                    TextEntry::make('membership_retention_until')
                        ->label('Retention until')
                        ->state(fn (?Application $record): string => $record?->profile?->membership?->retention_until?->toDateString() ?? '—'),
                    TextEntry::make('membership_lapsed_at')
                        ->label('Lapsed at')
                        ->state(fn (?Application $record): string => $record?->profile?->membership?->lapsed_at?->toDateTimeString() ?? '—'),
                    TextEntry::make('profile_public_status')
                        ->label('Profile public?')
                        ->state(function (?Application $record): string {
                            $profile = $record?->profile;
                            if (! $profile) {
                                return '—';
                            }

                            return app(ProfileUrlService::class)->isPubliclyVisible($profile) ? 'yes' : 'no';
                        }),
                ])
                ->columns(2)
                ->collapsed()
                ->visible(fn (?Application $record): bool => $record?->profile?->membership !== null),
        ]);
    }

    private static function primaryPhoto(?Application $record): ?MediaItem
    {
        return $record?->profile?->media?->first(
            fn (MediaItem $media): bool => (bool) $media->is_primary
                && $media->media_type === MediaItem::TYPE_PROFILE_PHOTO
                && $media->review_status === MediaItem::REVIEW_APPROVED
                && $media->privacy === MediaItem::PRIVACY_PUBLIC
                && filled($media->storage_path_key),
        );
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function canSeeAccountEmail(?Application $record): bool
    {
        $actor = self::actor();
        if (! $actor instanceof User || ! $record instanceof Application) {
            return false;
        }

        return $actor->can('viewAccountEmail', $record);
    }

    private static function canSeePrivateContact(?Application $record): bool
    {
        $actor = self::actor();
        if (! $actor instanceof User || ! $record instanceof Application) {
            return false;
        }

        return $actor->can('viewContactDetails', $record);
    }

    /**
     * Wayfinding banner: current state + next meaningful action, derived only
     * from existing workflow fields. Presentation mapping — no new semantics.
     *
     * @return array{state: string, action: string, tone: string}
     */
    public static function wayfindingFor(?Application $application): array
    {
        if ($application === null) {
            return ['state' => '—', 'action' => '—', 'tone' => 'muted'];
        }

        // An open post-publication maintenance cycle overrides the plain
        // published-state wayfinding: the banner reflects the cycle's stage
        // (derived from the authoritative request state), never inventing a
        // new workflow status.
        $maintenance = app(\App\Services\PostPublicationUpdateService::class)->openRequestFor($application);
        if ($maintenance !== null) {
            $classification = $maintenance->billing_classification === EditorialRevisionRequest::BILLING_COMPLIMENTARY
                ? 'Complimentary'
                : 'Paid';

            return match ((string) $maintenance->status) {
                EditorialRevisionRequest::STATUS_SUBMITTED => [
                    'state' => 'Maintenance update requested ('.$classification.')',
                    'action' => 'Editorial team: prepare the editorial update (AI draft or direct preparation).',
                    'tone' => 'info',
                ],
                EditorialRevisionRequest::STATUS_IN_PROGRESS => filled($maintenance->customer_correction_text)
                    ? [
                        'state' => 'Customer correction received ('.$classification.')',
                        'action' => "Review the customer's requested corrections and prepare corrected EN/ML versions.",
                        'tone' => 'warn',
                    ]
                    : [
                        'state' => 'Maintenance update in progress ('.$classification.')',
                        'action' => 'Editorial team: prepare the revised EN/ML versions.',
                        'tone' => 'info',
                    ],
                EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW => [
                    'state' => 'Maintenance preview awaiting customer ('.$classification.')',
                    'action' => 'Await customer approval or minor-correction request — no staff action required right now.',
                    'tone' => 'info',
                ],
                EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED => [
                    'state' => 'Customer approved maintenance update ('.$classification.')',
                    'action' => 'Admin: publish the approved update.',
                    'tone' => 'warn',
                ],
                default => [
                    'state' => 'Maintenance update ('.$classification.')',
                    'action' => 'Review the maintenance request state.',
                    'tone' => 'info',
                ],
            };
        }

        $labels = Application::workflowStatusLabels();
        $state = $labels[(string) $application->status] ?? (string) $application->status;

        return match ((string) $application->status) {
            Application::STATUS_INTAKE_IN_PROGRESS => ['state' => $state, 'action' => 'Customer: finish intake, then continue to payment.', 'tone' => 'muted'],
            Application::STATUS_INTERVIEW_IN_PROGRESS => ['state' => $state, 'action' => 'Customer: complete the Online Interview.', 'tone' => 'muted'],
            Application::STATUS_INTERVIEW_SUBMITTED, Application::STATUS_DIRECT_SUBMITTED => ['state' => $state, 'action' => 'Customer: complete payment to unlock editorial processing.', 'tone' => 'muted'],
            Application::STATUS_PAYMENT_PENDING => ['state' => $state, 'action' => 'Customer: pay — or Finance: apply a waiver for this application.', 'tone' => 'warn'],
            Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW => ['state' => $state, 'action' => 'Customer: start the Online Interview.', 'tone' => 'muted'],
            Application::STATUS_AWAITING_EDITORIAL_REVIEW => ['state' => $state, 'action' => 'Editorial team: prepare versions (AI draft or editorial writing), then release for customer preview.', 'tone' => 'info'],
            Application::STATUS_IN_EDITORIAL_REVIEW => ['state' => $state, 'action' => 'Editorial team: finish review and release the preview to the customer.', 'tone' => 'info'],
            Application::STATUS_EDITORIAL_APPROVED => ['state' => $state, 'action' => 'Customer: review the preview — approve for publication or request an included revision.', 'tone' => 'info'],
            Application::STATUS_EDITORIAL_REVISION_REQUESTED => ['state' => $state, 'action' => 'Editorial team: address the customer\'s requested changes, then release a fresh preview.', 'tone' => 'warn'],
            Application::STATUS_AWAITING_PUBLICATION => ['state' => $state, 'action' => 'Admin: publish the customer-approved version.', 'tone' => 'warn'],
            Application::STATUS_PUBLISHED => ['state' => $state, 'action' => 'None — the profile is live. Renewals follow the membership lifecycle.', 'tone' => 'ok'],
            default => ['state' => $state, 'action' => 'None — this application is closed.', 'tone' => 'muted'],
        };
    }

    /**
     * Rendered wayfinding banner (safe HTML: values come from fixed workflow
     * labels, never user input).
     */
    public static function workflowBannerHtml(?Application $application): string
    {
        $wayfinding = self::wayfindingFor($application);

        $tones = [
            'ok' => ['bg' => '#eaf4ec', 'border' => '#86a982', 'text' => '#33553f'],
            'info' => ['bg' => '#edf3f7', 'border' => '#52758a', 'text' => '#214d68'],
            'warn' => ['bg' => '#fffaf3', 'border' => '#c0762e', 'text' => '#7a4a1c'],
            'muted' => ['bg' => '#f4f6f3', 'border' => '#d3ddd1', 'text' => '#46554d'],
        ];
        $tone = $tones[$wayfinding['tone']] ?? $tones['muted'];

        return '<div style="border:1px solid '.$tone['border'].';background:'.$tone['bg'].';border-radius:10px;padding:14px 16px;margin-bottom:4px">'
            .'<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 14px">'
            .'<span style="font:600 10px/1.4 \'DM Sans\',sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#6f8075">Current state</span>'
            .'<span style="font:600 15px/1.4 \'Fraunces\',Georgia,serif;color:'.$tone['text'].'">'.e($wayfinding['state']).'</span>'
            .'</div>'
            .'<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 14px;margin-top:6px">'
            .'<span style="font:600 10px/1.4 \'DM Sans\',sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#6f8075">Next action</span>'
            .'<span style="font-size:13.5px;line-height:1.5;color:#1f2924">'.e($wayfinding['action']).'</span>'
            .'</div>'
            .'</div>';
    }

    /**
     * Rendered OLD → NEW comparison for an open maintenance request:
     * published content versus the prepared proposed version, per language.
     * Read-only presentation over immutable versions.
     */
    public static function maintenanceDiffHtml(?Application $application): string
    {
        $request = self::openMaintenanceRequest($application);
        if ($request === null || $application?->profile === null) {
            return '';
        }

        $pairs = [];
        foreach ([
            'English' => [
                \App\Models\EditorialContent::LANGUAGE_EN,
                $application->published_english_editorial_content_id,
                $request->resulting_english_editorial_content_id,
            ],
            'Malayalam' => [
                \App\Models\EditorialContent::LANGUAGE_ML,
                $application->published_malayalam_editorial_content_id,
                $request->resulting_malayalam_editorial_content_id,
            ],
        ] as [$label, $publishedId, $proposedId]) {
            if (! filled($proposedId)) {
                continue;
            }

            $published = filled($publishedId) ? \App\Models\EditorialContent::query()->find($publishedId) : null;
            $proposed = \App\Models\EditorialContent::query()->find($proposedId);
            if ($proposed === null) {
                continue;
            }

            $diff = \App\Support\TextDiffHighlighter::diff(
                (string) $published?->body,
                (string) $proposed->body,
            );

            $pairs[] = '<div style="margin:0 0 16px">'
                .'<div style="font:600 10.5px/1.4 \'DM Sans\',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#6f8075;margin-bottom:6px">'
                .$label.' — published v'.((int) ($published?->version_number ?? 0)).' → proposed v'.((int) $proposed->version_number).'</div>'
                .'<div style="border:1px solid #d3ddd1;border-radius:8px;padding:12px 14px;background:#fbf7f5;font-size:13px;line-height:1.7;color:#46554d;margin-bottom:8px">'
                .'<div style="font:600 10px/1.4 \'DM Sans\',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#8c2f24;margin-bottom:4px">Old — current published</div>'
                .$diff['old_html'].'</div>'
                .'<div style="border:1px solid #b7d4bd;border-radius:8px;padding:12px 14px;background:#f4faf5;font-size:13px;line-height:1.7;color:#1f2924">'
                .'<div style="font:600 10px/1.4 \'DM Sans\',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#2f6b3a;margin-bottom:4px">New — Jannayaks proposed</div>'
                .$diff['new_html'].'</div>'
                .'</div>';
        }

        if ($pairs === []) {
            return '<p style="margin:0;font-size:13.5px;color:#6f8075">No proposed version has been prepared yet.</p>';
        }

        return '<style>.jk-diff-del{background:#fdecea;color:#8c2f24;text-decoration:line-through;padding:0 2px;border-radius:3px}.jk-diff-ins{background:#e5f2e8;color:#2f6b3a;text-decoration:none;padding:0 2px;border-radius:3px;font-weight:600}</style>'
            .'<div>'.implode('', $pairs).'</div>';
    }

    /**
     * The open post-publication maintenance request for the workspace page.
     */
    public static function openMaintenanceRequest(?Application $application): ?EditorialRevisionRequest
    {
        if ($application === null) {
            return null;
        }

        return app(\App\Services\PostPublicationUpdateService::class)->openRequestFor($application);
    }

    /**
     * The revision/maintenance request entry — shared by the open-cycle and
     * history sections so both presentations stay identical.
     */
    private static function revisionRequestEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('application_revision_requests')
            ->label('')
            ->schema([
                TextEntry::make('round_number')->label('Round')->placeholder('—'),
                TextEntry::make('request_type')
                    ->label('Update type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE => 'warning',
                        EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION => 'gray',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE => 'Post-publication profile update',
                        EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION => 'Factual correction',
                        default => 'Revision (pre-publication)',
                    }),
                TextEntry::make('billing_classification')
                    ->label('Eligibility')
                    ->badge()
                    ->color(fn (?string $state): string => $state === EditorialRevisionRequest::BILLING_COMPLIMENTARY ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        EditorialRevisionRequest::BILLING_COMPLIMENTARY => 'Complimentary update',
                        EditorialRevisionRequest::BILLING_PAID => 'Paid update request',
                        default => '—',
                    })
                    ->placeholder('—'),
                TextEntry::make('eligibility_published_on')
                    ->label('Eligibility anchor (publication date)')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextEntry::make('next_eligible_on')
                    ->label('Next complimentary eligibility')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('request_text')
                    ->label('Customer requested changes')
                    ->columnSpanFull(),
                TextEntry::make('preview_english_editorial_content_id')
                    ->label('Applies to EN version')
                    ->formatStateUsing(function ($state): string {
                        if (! filled($state)) {
                            return '—';
                        }

                        return 'EN version #'.((int) $state).' — '.self::editorialVersionSummary((int) $state);
                    })
                    ->placeholder('—')
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                TextEntry::make('preview_malayalam_editorial_content_id')
                    ->label('Applies to ML version')
                    ->formatStateUsing(function ($state): string {
                        if (! filled($state)) {
                            return '—';
                        }

                        return 'ML version #'.((int) $state).' — '.self::editorialVersionSummary((int) $state);
                    })
                    ->placeholder('—')
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                TextEntry::make('staff_notes')->label('Staff notes')->placeholder('—'),
                TextEntry::make('processed_at')->label('Processed')->since()->placeholder('Not processed yet'),
                TextEntry::make('customer_correction_text')
                    ->label('Customer correction request (minor corrections on the proposed version)')
                    ->columnSpanFull()
                    ->placeholder('—'),
                TextEntry::make('resulting_english_editorial_content_id')
                    ->label('Prepared EN version')
                    ->formatStateUsing(fn ($state): string => filled($state) ? 'EN version #'.((int) $state).' — '.self::editorialVersionSummary((int) $state) : 'Not prepared yet')
                    ->placeholder('Not prepared yet')
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                TextEntry::make('resulting_malayalam_editorial_content_id')
                    ->label('Prepared ML version')
                    ->formatStateUsing(fn ($state): string => filled($state) ? 'ML version #'.((int) $state).' — '.self::editorialVersionSummary((int) $state) : 'Not prepared yet')
                    ->placeholder('Not prepared yet')
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                TextEntry::make('approved_english_editorial_content_id')
                    ->label('Customer-approved EN version')
                    ->formatStateUsing(fn ($state): string => filled($state) ? 'EN version #'.((int) $state) : 'Not approved yet')
                    ->placeholder('Not approved yet')
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
                TextEntry::make('approved_malayalam_editorial_content_id')
                    ->label('Customer-approved ML version')
                    ->formatStateUsing(fn ($state): string => filled($state) ? 'ML version #'.((int) $state) : 'Not approved yet')
                    ->placeholder('Not approved yet')
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) ? self::editorialContentViewUrl((int) $state) : null, shouldOpenInNewTab: true),
            ])
            ->columns(2)
            ->columnSpanFull();
    }

    public static function editorialContentViewUrl(int $contentId): string
    {
        return \App\Filament\Resources\EditorialContents\EditorialContentResource::getUrl('view', ['record' => $contentId]);
    }

    /**
     * Human-readable summary of a stored editorial version (label helper for
     * "Applies to EN/ML version" references).
     */
    public static function editorialVersionSummary(int $contentId): string
    {
        $content = EditorialContent::query()->find($contentId);
        if ($content === null) {
            return 'no longer stored';
        }

        $statusLabels = [
            EditorialContent::STATUS_DRAFT => 'draft',
            EditorialContent::STATUS_APPROVED => 'approved',
            EditorialContent::STATUS_ARCHIVED => 'archived',
        ];

        return ($statusLabels[(string) $content->status] ?? (string) $content->status)
            .', version '.((int) $content->version_number);
    }

    /**
     * Badge for the version currently bound to the customer-preview or
     * publication workflow, read from the application's existing binding IDs.
     */
    public static function editorialBindingFor(?EditorialContent $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $application = $content->profile?->application;
        if ($application === null) {
            return null;
        }

        if (in_array((int) $content->id, array_filter([
            (int) $application->published_english_editorial_content_id,
            (int) $application->published_malayalam_editorial_content_id,
        ]), true)) {
            return 'Published';
        }

        if (in_array((int) $content->id, array_filter([
            (int) $application->preview_english_editorial_content_id,
            (int) $application->preview_malayalam_editorial_content_id,
        ]), true)) {
            return 'Customer preview';
        }

        return null;
    }
}

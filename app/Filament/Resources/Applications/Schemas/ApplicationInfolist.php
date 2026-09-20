<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\Application;
use App\Models\User;
use App\Services\MembershipLifecycleService;
use App\Services\ProfileUrlService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Applicant')
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
                ->columns(2),
            Section::make('Workflow')
                ->schema([
                    TextEntry::make('status')
                        ->formatStateUsing(fn (?string $state): string => Application::workflowStatusLabels()[$state] ?? (string) $state),
                    TextEntry::make('package_tier'),
                    TextEntry::make('source_method'),
                    TextEntry::make('included_revision_rounds_used')
                        ->label('Included revision rounds used')
                        ->formatStateUsing(fn (?int $state): string => ((int) $state).' / 2'),
                    TextEntry::make('customer_preview_released_at')->dateTime()->placeholder('—'),
                    TextEntry::make('preview_english_editorial_content_id')->label('Preview EN version')->placeholder('—'),
                    TextEntry::make('customer_approved_at')->dateTime()->placeholder('—'),
                    TextEntry::make('customer_approved_english_editorial_content_id')->label('Approved EN version')->placeholder('—'),
                    TextEntry::make('payment_status')
                        ->visible(fn (): bool => self::actor()?->canManageFinance() ?? false),
                    TextEntry::make('payment_settled_at')->dateTime(),
                    TextEntry::make('online_interview_completed_at')->dateTime(),
                    TextEntry::make('direct_submission_received_at')->dateTime(),
                ])
                ->columns(2),
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
}

<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\MediaItem;
use App\Models\PhotoEnhancementRun;
use App\Models\Profile;
use App\Models\User;
use App\Services\PhotoEnhancementService;
use App\Services\ProfileMediaService;
use App\Support\PhotographReplacementComparison;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProfileMediaRelationManager extends RelationManager
{
    protected static string $relationship = 'profilePhotos';

    protected static ?string $title = 'Profile photographs';

    public function table(Table $table): Table
    {
        return $table
            ->description('Each row is the customer’s original photograph. The AI enhancement column shows whether a run is waiting, processing, ready, failed, or simulated. Use the compare action on that row to view the original and the candidate: side by side on a wide screen and stacked on a phone. The original stays private until you choose it, and a candidate is not the public primary photograph until you accept it.')
            ->stackedOnMobile()
            ->columns([
                ImageColumn::make('staff_preview_thumbnail')
                    ->label('')
                    ->state(fn (MediaItem $record): ?string => filled($record->storage_path_key)
                        ? route('staff.profile-media.preview', ['media' => $record])
                        : null)
                    ->height(52)
                    ->width(40)
                    ->square(false)
                    ->extraImgAttributes(['style' => 'border:1px solid #d3ddd1;border-radius:6px;background:#eaf2e7;object-fit:cover']),
                TextColumn::make('enhancement_status')
                    ->label('AI enhancement')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => (string) $state)
                    ->color(fn (?string $state): string => match (true) {
                        str_contains((string) $state, 'failed') => 'danger',
                        str_contains((string) $state, 'simulated') => 'warning',
                        str_starts_with((string) $state, 'Ready for review') => 'success',
                        str_starts_with((string) $state, 'Queued') || str_starts_with((string) $state, 'Processing') => 'info',
                        default => 'gray',
                    })
                    ->state(function (MediaItem $record): ?string {
                        $service = app(PhotoEnhancementService::class);
                        $run = $service->latestRunFor($record);
                        if ($run === null) {
                            return $service->enabled() ? 'Not started' : 'Enhancement off';
                        }

                        $label = $run->statusLabel();
                        if ($run->provider === 'fake') {
                            $label .= ' · simulated';
                        }

                        return $label;
                    }),
                TextColumn::make('review_status')->badge()->label('Review'),
                TextColumn::make('customer_visible_role')
                    ->label('Customer-visible role')
                    ->badge()
                    ->state(function (MediaItem $record): string {
                        if ($record->review_status === MediaItem::REVIEW_APPROVED && $record->privacy === MediaItem::PRIVACY_PUBLIC) {
                            return $record->is_primary ? 'Currently public · primary' : 'Currently public';
                        }

                        if ($record->review_status === MediaItem::REVIEW_PENDING) {
                            return 'Proposed — not public until approved';
                        }

                        if ($record->enhanced_from_media_id !== null && $record->review_status === MediaItem::REVIEW_APPROVED) {
                            return 'Accepted replacement';
                        }

                        return 'Not public';
                    }),
                IconColumn::make('is_primary')->boolean()->label('Primary req.'),
                TextColumn::make('privacy')->badge(),
                TextColumn::make('id')->label('ID')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('width')->label('W')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('height')->label('H')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('size_bytes')->numeric()->label('Bytes')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('replacementComparison')
                    ->label('Compare photographs')
                    ->color('gray')
                    ->modalHeading('Current photograph and proposed replacement')
                    ->modalDescription('The proposed photograph is not published by opening this comparison. The current photograph remains in use until the replacement is approved.')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (MediaItem $record) => view('filament.relation-managers.photo-replacement-comparison', [
                        'record' => $record,
                    ]))
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && PhotographReplacementComparison::isAvailable($record)),
                Action::make('enhancementComparison')
                    ->label('Compare AI enhancement')
                    ->color('gray')
                    ->modalHeading('Customer source vs AI enhanced candidate')
                    ->modalDescription('The candidate is a suggestion. A simulated provider is not genuine AI. The candidate is never public until you accept it.')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (MediaItem $record) => view('filament.relation-managers.photo-enhancement-comparison', [
                        'source' => $record,
                        'run' => app(PhotoEnhancementService::class)->latestRunFor($record),
                    ]))
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions(fn (MediaItem $record): array => [
                        Action::make('acceptEnhancedFromCompare')
                            ->label('Accept Enhanced')
                            ->color('success')
                            ->visible(fn (): bool => $this->candidateReadyFor($record))
                            ->action(fn () => $this->decideEnhancement($record, 'accept')),
                        Action::make('keepOriginalFromCompare')
                            ->label('Keep Original')
                            ->color('gray')
                            ->visible(fn (): bool => $this->candidateReadyFor($record))
                            ->action(fn () => $this->decideEnhancement($record, 'keep')),
                        Action::make('regenerateFromCompare')
                            ->label('Regenerate')
                            ->color('warning')
                            ->visible(fn (): bool => $this->candidateReadyFor($record))
                            ->action(fn () => $this->decideEnhancement($record, 'regenerate')),
                    ])
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && app(PhotoEnhancementService::class)->latestRunFor($record) !== null),
                Action::make('acceptEnhanced')
                    ->label('Accept Enhanced')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Accept the AI enhanced photograph')
                    ->modalDescription('The enhanced candidate becomes the approved public primary photograph. The customer source is retained privately for audit and fallback — nothing is deleted.')
                    ->visible(fn (MediaItem $record): bool => $this->candidateReadyFor($record))
                    ->action(fn (MediaItem $record) => $this->decideEnhancement($record, 'accept')),
                Action::make('keepOriginal')
                    ->label('Keep Original')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Keep the customer original')
                    ->modalDescription('The optimized customer source becomes the approved public photograph. The AI candidate and its stored file are discarded.')
                    ->visible(fn (MediaItem $record): bool => $this->candidateReadyFor($record))
                    ->action(fn (MediaItem $record) => $this->decideEnhancement($record, 'keep')),
                Action::make('regenerateEnhanced')
                    ->label('Regenerate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate the enhancement')
                    ->modalDescription('The current candidate is discarded (including its stored file) and a fresh enhancement run starts from the same customer source.')
                    ->visible(fn (MediaItem $record): bool => $this->candidateReadyFor($record))
                    ->action(fn (MediaItem $record) => $this->decideEnhancement($record, 'regenerate')),
                Action::make('retryEnhancement')
                    ->label('Retry')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry the failed enhancement')
                    ->modalDescription('The original customer photograph remains available and untouched. A new enhancement attempt will be queued.')
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && ($run = app(PhotoEnhancementService::class)->latestRunFor($record)) !== null
                        && $run->status === PhotoEnhancementRun::STATUS_FAILED)
                    ->action(function (MediaItem $record): void {
                        $service = app(PhotoEnhancementService::class);
                        $run = $service->latestRunFor($record);
                        if ($run === null) {
                            return;
                        }
                        $service->retry($run, auth()->user());
                        Notification::make()->title('Enhancement retry queued')->success()->send();
                    }),
                Action::make('preview')
                    ->label('Inspect')
                    ->url(fn (MediaItem $record): string => route('staff.profile-media.preview', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => auth()->user()?->canManageEditorial() ?? false),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && $record->review_status === MediaItem::REVIEW_PENDING)
                    ->form([
                        Textarea::make('review_note')->label('Note (optional)')->rows(3),
                    ])
                    ->action(function (MediaItem $record, array $data): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            return;
                        }
                        app(ProfileMediaService::class)->approve(
                            $record,
                            $user,
                            $data['review_note'] ?? null,
                        );
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && $record->review_status === MediaItem::REVIEW_PENDING)
                    ->form([
                        Textarea::make('review_note')->label('Note (optional)')->rows(3),
                    ])
                    ->action(function (MediaItem $record, array $data): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            return;
                        }
                        app(ProfileMediaService::class)->reject(
                            $record,
                            $user,
                            $data['review_note'] ?? null,
                        );
                    }),
                Action::make('makePrimary')
                    ->label('Set primary request')
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && ! $record->is_primary
                        && $record->review_status !== MediaItem::REVIEW_REJECTED)
                    ->action(function (MediaItem $record): void {
                        $user = auth()->user();
                        $profile = $this->getOwnerRecord()->profile;
                        if (! $user instanceof User || ! $profile instanceof Profile) {
                            return;
                        }
                        app(ProfileMediaService::class)->setPrimary($profile, $record, $user);
                    }),
                Action::make('remove')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => auth()->user()?->canManageEditorial() ?? false)
                    ->action(function (MediaItem $record): void {
                        $user = auth()->user();
                        $profile = $this->getOwnerRecord()->profile;
                        if (! $user instanceof User || ! $profile instanceof Profile) {
                            return;
                        }
                        app(ProfileMediaService::class)->deletePhoto($profile, $record, $user);
                    }),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageEditorial() && filled($ownerRecord->profile_id);
    }

    /** True when this source row has a completed run with a pending candidate. */
    private function candidateReadyFor(MediaItem $record): bool
    {
        if (! (auth()->user()?->canManageEditorial() ?? false)) {
            return false;
        }

        $run = app(PhotoEnhancementService::class)->activeRunFor($record);

        return $run !== null
            && $run->status === PhotoEnhancementRun::STATUS_COMPLETED
            && $run->candidate_media_id !== null;
    }

    private function decideEnhancement(MediaItem $record, string $decision): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $service = app(PhotoEnhancementService::class);
        $run = $service->activeRunFor($record);
        $candidate = $run?->candidate;
        if ($run === null || ! $candidate instanceof MediaItem) {
            Notification::make()->title('No active enhancement candidate')->warning()->send();

            return;
        }

        try {
            match ($decision) {
                'accept' => $service->accept($candidate, $user),
                'keep' => $service->keepOriginal($candidate, $user),
                'regenerate' => $service->regenerate($candidate, $user),
                default => null,
            };

            Notification::make()
                ->title(match ($decision) {
                    'accept' => 'Enhanced photograph accepted',
                    'keep' => 'Customer original kept',
                    'regenerate' => 'Enhancement regeneration queued',
                    default => 'Done',
                })
                ->success()
                ->send();
        } catch (\ValueError $e) {
            Notification::make()->title('Cannot decide enhancement candidate')->body($e->getMessage())->danger()->send();
        } catch (\Throwable) {
            Notification::make()->title('Enhancement action failed')->danger()->send();
        }
    }
}

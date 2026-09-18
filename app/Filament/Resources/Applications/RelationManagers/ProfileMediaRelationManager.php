<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileMediaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
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
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('review_status')->badge()->label('Review'),
                IconColumn::make('is_primary')->boolean()->label('Primary req.'),
                TextColumn::make('privacy')->badge(),
                TextColumn::make('width')->label('W'),
                TextColumn::make('height')->label('H'),
                TextColumn::make('size_bytes')->numeric()->label('Bytes'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
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
}

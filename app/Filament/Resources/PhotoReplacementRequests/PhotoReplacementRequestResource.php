<?php

namespace App\Filament\Resources\PhotoReplacementRequests;

use App\Filament\Resources\PhotoReplacementRequests\Pages\ManagePhotoReplacementRequests;
use App\Models\PendingPhotoReplacement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Security Administrator review inbox for member-submitted photograph
 * replacements. Visible and operable ONLY through the published-profile
 * authority (transitional legacy-admin override until strict separation).
 * Approval runs the authorised media workflow; flagged submissions require
 * explicit manual-examination confirmation.
 */
class PhotoReplacementRequestResource extends Resource
{
    protected static ?string $model = PendingPhotoReplacement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Photo replacement requests';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextColumn::make('id')->label('Request ID'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('profile.slug')->label('Profile slug')->searchable()->sortable(),
                TextColumn::make('member.email')->label('Submitted by')->searchable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
                TextColumn::make('screening_result')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'clear' ? 'clear' : 'flagged')
                    ->color(fn (string $state): string => $state === 'clear' ? 'success' : 'warning'),
                TextColumn::make('moderation_result')->label('Content moderation')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'clear' => 'success',
                        'flagged' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(['submitted' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                SelectFilter::make('screening_result')
                    ->options(['clear' => 'Clear', 'flagged' => 'Flagged']),
            ])
            ->recordActions([
                Action::make('approve')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn (PendingPhotoReplacement $record): bool => $record->status === 'submitted')
                    ->form([
                        Textarea::make('review_note')->label('Review note (optional)')->maxLength(1000),
                        Toggle::make('confirm_manual_examination')
                            ->label('I have manually examined this image (required for flagged screening or flagged/uncertain content moderation)')
                            ->visible(fn (PendingPhotoReplacement $record): bool => $record->screening_result === 'flagged'
                                || in_array($record->moderation_result, ['flagged', 'uncertain'], true))
                            ->required(fn (PendingPhotoReplacement $record): bool => $record->screening_result === 'flagged'
                                || in_array($record->moderation_result, ['flagged', 'uncertain'], true)),
                    ])
                    ->action(function (PendingPhotoReplacement $record, array $data): void {
                        app(\App\Services\PhotoReplacementRequestService::class)->approve(
                            $record,
                            auth()->user(),
                            (string) ($data['review_note'] ?? ''),
                            (bool) ($data['confirm_manual_examination'] ?? false),
                        );
                    }),
                Action::make('reject')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (PendingPhotoReplacement $record): bool => $record->status === 'submitted')
                    ->form([
                        Textarea::make('review_note')->label('Reason given to the member (optional)')->maxLength(1000),
                    ])
                    ->action(function (PendingPhotoReplacement $record, array $data): void {
                        app(\App\Services\PhotoReplacementRequestService::class)->reject(
                            $record,
                            auth()->user(),
                            (string) ($data['review_note'] ?? ''),
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePhotoReplacementRequests::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManagePublishedProfiles();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Review happens only through the approve/reject actions.
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}

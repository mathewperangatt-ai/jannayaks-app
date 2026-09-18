<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\ProfileExternalLink;
use App\Models\User;
use App\Services\ExternalVideoLinkService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProfileExternalLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'profileExternalLinks';

    protected static ?string $title = 'External video links';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('label')->placeholder('—'),
                TextColumn::make('url')->limit(48)->wrap(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_publicly_active')->boolean()->label('Live'),
                TextColumn::make('replaces_link_id')->label('Replaces')->placeholder('—'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->visible(fn (ProfileExternalLink $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && $record->status === ProfileExternalLink::STATUS_PENDING_REVIEW)
                    ->form([
                        Textarea::make('review_note')->label('Note (optional)')->rows(3),
                    ])
                    ->action(function (ProfileExternalLink $record, array $data): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            return;
                        }
                        app(ExternalVideoLinkService::class)->approve(
                            $record,
                            $user,
                            $data['review_note'] ?? null,
                        );
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (ProfileExternalLink $record): bool => (auth()->user()?->canManageEditorial() ?? false)
                        && $record->status === ProfileExternalLink::STATUS_PENDING_REVIEW)
                    ->form([
                        Textarea::make('review_note')->label('Note (optional)')->rows(3),
                    ])
                    ->action(function (ProfileExternalLink $record, array $data): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            return;
                        }
                        app(ExternalVideoLinkService::class)->reject(
                            $record,
                            $user,
                            $data['review_note'] ?? null,
                        );
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

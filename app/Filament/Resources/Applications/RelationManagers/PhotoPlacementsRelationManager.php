<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\EditorialPhotoPlacement;
use App\Models\User;
use App\Services\PhotoPlacementService;
use App\Support\AdminFeedback;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Editorial photograph placement management (Phase 3). Administrators
 * review AI recommendations, accept or override them, reorder, caption,
 * and remove placements — without ever touching the underlying photograph.
 */
class PhotoPlacementsRelationManager extends RelationManager
{
    protected static string $relationship = 'photoPlacements';

    public function table(Table $table): Table
    {
        return $table
            ->description('Editorial photograph placement — where each approved photograph appears in the publication-ready presentation. The AI recommends; you decide. Placements never modify the photographs themselves.')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('Photograph')
                    ->getStateUsing(fn ($record): ?string => $record->mediaItem !== null
                        ? route('staff.profile-media.preview', ['media' => $record->mediaItem])
                        : null)
                    ->imageHeight(56),
                TextColumn::make('media_item_id')->label('Media #'),
                TextColumn::make('placement_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hero' => 'success',
                        'inline' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('section_anchor')->label('Section')->placeholder('—'),
                TextColumn::make('display_order')->label('Order'),
                TextColumn::make('caption_en')->label('Caption (EN)')->limit(40)->placeholder('—'),
                TextColumn::make('caption_ml')->label('Caption (ML)')->limit(40)->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'approved' ? 'success' : 'warning'),
                TextColumn::make('source')->badge()->label('Source'),
            ])
            ->defaultSort('display_order')
            ->recordActions([
                \Filament\Tables\Actions\Action::make('editPlacement')
                    ->label('Decide / override')
                    ->icon('heroicon-m-pencil-square')
                    ->visible(fn ($record): bool => $this->canManage() && $record->mediaItem !== null)
                    ->form(fn ($record): array => [
                        Select::make('placement_type')
                            ->options([
                                'hero' => 'Hero (framed portrait)',
                                'inline' => 'Inline (within a narrative section)',
                                'gallery' => 'Gallery (documentary record)',
                            ])
                            ->default($record->placement_type)
                            ->required(),
                        Select::make('section_anchor')
                            ->label('Section anchor (stable across languages)')
                            ->options([
                                'biography' => 'Biography',
                                'career' => 'Career & Responsibilities',
                                'gallery' => 'Gallery',
                            ])
                            ->default($record->section_anchor ?? 'biography')
                            ->required()
                            ->visible(fn (\Filament\Forms\Get $get): bool => $get('placement_type') !== 'hero'),
                        TextInput::make('display_order')
                            ->numeric()->minValue(1)->maxValue(9)
                            ->default($record->display_order)->required(),
                        Textarea::make('caption_en')
                            ->label('Caption (English) — optional; must restate a fact from the narrative')
                            ->maxLength(300)
                            ->default($record->caption_en),
                        Textarea::make('caption_ml')
                            ->label('Caption (Malayalam) — optional; reviewed wording only')
                            ->maxLength(300)
                            ->default($record->caption_ml),
                    ])
                    ->action(function (array $data, $record): void {
                        $actor = auth()->user();
                        if (! $actor instanceof User) {
                            abort(403);
                        }
                        try {
                            app(PhotoPlacementService::class)->decidePlacement(
                                $record,
                                $actor,
                                (string) $data['placement_type'],
                                (string) ($data['section_anchor'] ?? 'gallery'),
                                (int) $data['display_order'],
                                $data['caption_en'] ?? null,
                                $data['caption_ml'] ?? null,
                            );
                            AdminFeedback::success('Placement updated', 'The presentation fingerprint changes — the customer must review and approve the new presentation before publication.');
                        } catch (InvalidArgumentException | ValidationException $e) {
                            AdminFeedback::danger('Cannot update placement', $e->getMessage());
                        }
                    }),
                \Filament\Tables\Actions\Action::make('removePlacement')
                    ->label('Remove placement')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Remove this photograph from the presentation? The photograph itself, its approval state and its storage are not touched.')
                    ->visible(fn ($record): bool => $this->canManage())
                    ->action(function ($record): void {
                        $actor = auth()->user();
                        if (! $actor instanceof User) {
                            abort(403);
                        }
                        app(PhotoPlacementService::class)->removePlacement($record, $actor);
                        AdminFeedback::success('Placement removed', 'The photograph stays on the media record and can be placed again.');
                    }),
            ])
            ->recordUrl(null);
    }

    public static function canViewForRecord(Model $ownerRecord, string $panelId): bool
    {
        return $ownerRecord instanceof Model
            && filled($ownerRecord->profile_id)
            && (auth()->user()?->canManageEditorial() ?? false);
    }

    private function canManage(): bool
    {
        return auth()->user()?->canManageEditorial() ?? false;
    }
}

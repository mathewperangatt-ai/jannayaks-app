<?php

namespace App\Filament\Resources\InMemoriamProfiles\RelationManagers;

use App\Models\InMemoriamProfile;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\InMemoriamMediaService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MemorialMediaRelationManager extends RelationManager
{
    protected static string $relationship = 'photographs';

    protected static ?string $title = 'Photographs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('review_status')->badge(),
                IconColumn::make('is_primary')->boolean(),
                TextColumn::make('privacy')->badge(),
                TextColumn::make('display_order'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('display_order')
            ->headerActions([
                Action::make('upload')
                    ->label('Upload photograph')
                    ->visible(fn (): bool => auth()->user()?->can('manageMedia', $this->getOwnerRecord()) ?? false)
                    ->form([
                        FileUpload::make('photo')
                            ->image()
                            ->required()
                            ->disk('local')
                            ->directory('tmp-in-memoriam-uploads')
                            ->visibility('private'),
                        Textarea::make('alt_text')->rows(2),
                        Toggle::make('make_primary')->label('Primary photograph'),
                    ])
                    ->action(function (array $data): void {
                        $actor = auth()->user();
                        /** @var InMemoriamProfile $owner */
                        $owner = $this->getOwnerRecord();
                        if (! $actor instanceof User) {
                            return;
                        }
                        $path = $data['photo'] ?? null;
                        if (! is_string($path) || $path === '') {
                            Notification::make()->title('Upload missing')->danger()->send();

                            return;
                        }
                        $full = storage_path('app/private/'.$path);
                        if (! is_file($full)) {
                            $full = storage_path('app/'.$path);
                        }
                        if (! is_file($full)) {
                            Notification::make()->title('Uploaded file not found')->danger()->send();

                            return;
                        }
                        $upload = new UploadedFile($full, basename($full), mime_content_type($full) ?: 'image/jpeg', null, true);
                        try {
                            app(InMemoriamMediaService::class)->uploadPhotograph(
                                $owner,
                                $upload,
                                $actor,
                                $data['alt_text'] ?? null,
                                (bool) ($data['make_primary'] ?? false),
                            );
                            @unlink($full);
                            Notification::make()->title('Uploaded (pending approval)')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title('Upload failed')->body(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->url(fn (MediaItem $record): string => route('staff.profile-media.preview', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => auth()->user()?->canManageEditorial() ?? false),
                Action::make('approve')
                    ->color('success')
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->can('approveMedia', $this->getOwnerRecord()) ?? false)
                        && $record->review_status === MediaItem::REVIEW_PENDING)
                    ->action(function (MediaItem $record): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            return;
                        }
                        try {
                            app(InMemoriamMediaService::class)->approve($record, $user);
                            Notification::make()->title('Approved')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title('Approve failed')->body(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn (MediaItem $record): bool => (auth()->user()?->can('manageMedia', $this->getOwnerRecord()) ?? false)
                        && $record->review_status === MediaItem::REVIEW_PENDING)
                    ->action(function (MediaItem $record): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            return;
                        }
                        try {
                            app(InMemoriamMediaService::class)->reject($record, $user);
                            Notification::make()->title('Rejected')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title('Reject failed')->body(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canViewApplicationQueue();
    }
}

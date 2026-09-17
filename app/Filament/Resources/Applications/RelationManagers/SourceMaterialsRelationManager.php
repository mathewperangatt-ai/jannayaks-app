<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\SourceMaterial;
use App\Models\User;
use App\Services\StaffAuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SourceMaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'sourceMaterials';

    protected static ?string $title = 'Source materials';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('admin_notes')
                ->label('Internal staff notes')
                ->rows(3)
                ->visible(fn (): bool => auth()->user()?->canManageEditorial() ?? false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('material_type')->badge(),
                TextColumn::make('original_filename')->label('File')->wrap(),
                TextColumn::make('mime_type')->toggleable(),
                TextColumn::make('file_bytes')->label('Bytes')->numeric(),
                TextColumn::make('uploaded_at')->dateTime()->sortable(),
                TextColumn::make('purged_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('uploaded_at', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->url(fn (SourceMaterial $record): string => route('staff.source-materials.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (SourceMaterial $record): bool => Gate::allows('download', $record)),
                Action::make('editNotes')
                    ->label('Notes')
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Internal staff notes')
                            ->rows(4),
                    ])
                    ->fillForm(fn (SourceMaterial $record): array => [
                        'admin_notes' => $record->admin_notes,
                    ])
                    ->action(function (SourceMaterial $record, array $data): void {
                        Gate::authorize('update', $record);
                        $before = ['admin_notes' => $record->admin_notes];
                        $record->forceFill([
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ])->save();
                        app(StaffAuditLogger::class)->log(
                            action: 'source_material.admin_notes_update',
                            subject: $record,
                            before: $before,
                            after: ['admin_notes' => $record->admin_notes],
                        );
                    })
                    ->visible(fn (SourceMaterial $record): bool => Gate::allows('update', $record)),
            ])
            ->toolbarActions([]);
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessSourceMaterial();
    }
}

<?php

namespace App\Filament\Resources\StaffActionLogs;

use App\Filament\Resources\StaffActionLogs\Pages\ListStaffActionLogs;
use App\Filament\Resources\StaffActionLogs\Pages\ViewStaffActionLog;
use App\Models\StaffActionLog;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StaffActionLogResource extends Resource
{
    protected static ?string $model = StaffActionLog::class;

    protected static ?string $slug = 'staff-action-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Audit history';

    protected static ?int $navigationSort = 30;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Audit record')
                ->description('Read only. Payload bodies and contact details are not shown.')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('action'),
                    TextEntry::make('actor.name')->label('Administrator'),
                    TextEntry::make('subject_type')->label('Subject type'),
                    TextEntry::make('subject_id')->label('Subject id'),
                    TextEntry::make('recorded_reason')->label('Recorded reason')->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('action')->searchable(),
                TextColumn::make('actor.name')->label('Administrator'),
                TextColumn::make('subject_type')->label('Subject')->toggleable(),
                TextColumn::make('subject_id')->label('Subject id'),
                TextColumn::make('recorded_reason')->label('Reason')->limit(80),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffActionLogs::route('/'),
            'view' => ViewStaffActionLog::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', StaffActionLog::class);
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('view', $record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}

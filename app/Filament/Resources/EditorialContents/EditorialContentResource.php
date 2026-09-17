<?php

namespace App\Filament\Resources\EditorialContents;

use App\Filament\Resources\EditorialContents\Pages\CreateEditorialContent;
use App\Filament\Resources\EditorialContents\Pages\EditEditorialContent;
use App\Filament\Resources\EditorialContents\Pages\ListEditorialContents;
use App\Filament\Resources\EditorialContents\Pages\ViewEditorialContent;
use App\Filament\Resources\EditorialContents\Schemas\EditorialContentForm;
use App\Filament\Resources\EditorialContents\Schemas\EditorialContentInfolist;
use App\Filament\Resources\EditorialContents\Tables\EditorialContentsTable;
use App\Models\EditorialContent;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EditorialContentResource extends Resource
{
    protected static ?string $model = EditorialContent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Editorial';

    protected static ?string $navigationLabel = 'Editorial contents';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return EditorialContentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EditorialContentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EditorialContentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEditorialContents::route('/'),
            'create' => CreateEditorialContent::route('/create'),
            'view' => ViewEditorialContent::route('/{record}'),
            'edit' => EditEditorialContent::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', EditorialContent::class);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('create', EditorialContent::class);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('delete', $record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}

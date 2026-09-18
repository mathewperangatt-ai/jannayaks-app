<?php

namespace App\Filament\Resources\InMemoriamProfiles;

use App\Filament\Resources\InMemoriamProfiles\Pages\CreateInMemoriamProfile;
use App\Filament\Resources\InMemoriamProfiles\Pages\EditInMemoriamProfile;
use App\Filament\Resources\InMemoriamProfiles\Pages\ListInMemoriamProfiles;
use App\Filament\Resources\InMemoriamProfiles\Pages\ViewInMemoriamProfile;
use App\Filament\Resources\InMemoriamProfiles\RelationManagers\MemorialEditorialRelationManager;
use App\Filament\Resources\InMemoriamProfiles\RelationManagers\MemorialMediaRelationManager;
use App\Filament\Resources\InMemoriamProfiles\Schemas\InMemoriamProfileForm;
use App\Filament\Resources\InMemoriamProfiles\Schemas\InMemoriamProfileInfolist;
use App\Filament\Resources\InMemoriamProfiles\Tables\InMemoriamProfilesTable;
use App\Models\InMemoriamProfile;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InMemoriamProfileResource extends Resource
{
    protected static ?string $model = InMemoriamProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'In Memoriam';

    protected static ?string $modelLabel = 'In Memoriam memorial';

    protected static ?string $pluralModelLabel = 'In Memoriam memorials';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'deceased_full_name';

    public static function form(Schema $schema): Schema
    {
        return InMemoriamProfileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InMemoriamProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InMemoriamProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MemorialEditorialRelationManager::class,
            MemorialMediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInMemoriamProfiles::route('/'),
            'create' => CreateInMemoriamProfile::route('/create'),
            'view' => ViewInMemoriamProfile::route('/{record}'),
            'edit' => EditInMemoriamProfile::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', InMemoriamProfile::class);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('create', InMemoriamProfile::class);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

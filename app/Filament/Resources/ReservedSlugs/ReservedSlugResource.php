<?php

namespace App\Filament\Resources\ReservedSlugs;

use App\Filament\Resources\ReservedSlugs\Pages\ManageReservedSlugs;
use App\Models\ReservedSlug;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ReservedSlugResource extends Resource
{
    protected static ?string $model = ReservedSlug::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Reserved profile URLs';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->maxLength(128)
                    ->rule('regex:/^[a-z0-9]+(?:[.\-][a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state))),
                Select::make('category')
                    ->options(ReservedSlug::categoryOptions())
                    ->required()
                    ->native(false),
                Textarea::make('reason')
                    ->maxLength(255)
                    ->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('slug')
            ->columns([
                TextColumn::make('slug')->searchable()->sortable(),
                TextColumn::make('category')->badge()->sortable(),
                TextColumn::make('reason')->limit(40)->wrap(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('slug')
            ->filters([
                SelectFilter::make('category')
                    ->options(ReservedSlug::categoryOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReservedSlugs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', ReservedSlug::class);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('create', ReservedSlug::class);
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

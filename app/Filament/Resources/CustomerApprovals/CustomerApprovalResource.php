<?php

namespace App\Filament\Resources\CustomerApprovals;

use App\Filament\Resources\CustomerApprovals\Pages\ListCustomerApprovals;
use App\Filament\Resources\CustomerApprovals\Pages\ViewCustomerApproval;
use App\Models\EditorialCustomerApproval;
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

class CustomerApprovalResource extends Resource
{
    protected static ?string $model = EditorialCustomerApproval::class;

    protected static ?string $slug = 'customer-approvals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Customer consent';

    protected static ?int $navigationSort = 31;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer consent')
                ->description('Read only. Names, emails, and editorial text are not shown.')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('application_id')->label('Application'),
                    TextEntry::make('profile_id')->label('Profile'),
                    TextEntry::make('consent_scope')->label('Consent scope'),
                    TextEntry::make('english_editorial_content_id')->label('English version'),
                    TextEntry::make('malayalam_editorial_content_id')->label('Malayalam version'),
                    TextEntry::make('approved_at')->dateTime(),
                    TextEntry::make('invalidated_at')->dateTime()->placeholder('Active'),
                    TextEntry::make('invalidation_reason')->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('application_id')->label('Application'),
                TextColumn::make('profile_id')->label('Profile'),
                TextColumn::make('consent_scope')->label('Scope'),
                TextColumn::make('approved_at')->dateTime()->sortable(),
                TextColumn::make('invalidated_at')->dateTime()->placeholder('Active'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerApprovals::route('/'),
            'view' => ViewCustomerApproval::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', EditorialCustomerApproval::class);
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

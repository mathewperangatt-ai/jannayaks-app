<?php

namespace App\Filament\Resources\InMemoriamProfiles\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InMemoriamProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('deceased_full_name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('slug')->toggleable(),
                TextColumn::make('verification_status')->badge()->toggleable(),
                IconColumn::make('is_sealed')->boolean(),
                TextColumn::make('hosting_ends_on')->date()->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}

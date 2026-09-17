<?php

namespace App\Filament\Resources\EditorialContents\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EditorialContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('profile_id')->label('Profile')->sortable(),
                TextColumn::make('language')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('version_number')->label('Ver')->sortable(),
                TextColumn::make('title')->searchable()->limit(40),
                IconColumn::make('ai_generated')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('language')
                    ->options([
                        'en' => 'English',
                        'ml' => 'Malayalam',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'archived' => 'Archived',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}

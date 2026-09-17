<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterviewAnswersRelationManager extends RelationManager
{
    protected static string $relationship = 'interviewAnswers';

    protected static ?string $title = 'Interview answers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question_id')->label('Question')->sortable(),
                TextColumn::make('original_answer')->label('Answer')->wrap()->limit(120),
                TextColumn::make('answered_at')->dateTime()->sortable(),
            ])
            ->defaultSort('question_id')
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->canAccessSourceMaterial() ?? false;
    }
}
